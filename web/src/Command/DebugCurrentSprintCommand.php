<?php

namespace App\Command;

use App\Repository\SprintRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:debug:current-sprint',
    description: 'Debug current sprint queries',
)]
class DebugCurrentSprintCommand extends Command
{
    public function __construct(
        private readonly SprintRepository $sprintRepository,
        private readonly HttpClientInterface $jiraClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🐛 Debug Current Sprint Queries');

        try {
            // Récupérer le sprint courant
            $currentSprint = $this->sprintRepository->findCurrentSprint();
            
            if (!$currentSprint) {
                $io->warning('Aucun sprint trouvé dans la base de données');
                return Command::SUCCESS;
            }

            $io->section('Sprint Info');
            $io->text("Sprint: {$currentSprint->getName()}");
            $io->text("Jira ID: {$currentSprint->getJiraId()}");
            $io->text("Période: {$currentSprint->getStartDate()->format('Y-m-d')} à {$currentSprint->getEndDate()->format('Y-m-d')}");

            // Test 1: Tous les tickets du sprint
            $io->section('Test 1: Tous les tickets du sprint');
            $jql1 = sprintf('project = "MD" AND sprint = %d', $currentSprint->getJiraId());
            $io->text("JQL: $jql1");
            
            try {
                $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', [
                    'query' => [
                        'jql' => $jql1,
                        'maxResults' => 5,
                        'fields' => 'key,summary,status,issuetype'
                    ]
                ]);
                $data = $response->toArray();
                $io->text("Total tickets: " . ($data['total'] ?? 'N/A'));
                
                if (!empty($data['issues'])) {
                    $io->text("Exemples de tickets:");
                    foreach (array_slice($data['issues'], 0, 3) as $issue) {
                        $fields = $issue['fields'] ?? [];
                        $io->text("- {$issue['key']}: {$fields['summary']} (Status: {$fields['status']['name']}, Type: {$fields['issuetype']['name']})");
                    }
                }
            } catch (\Exception $e) {
                $io->error("Erreur: " . $e->getMessage());
            }

            // Test 2: Bug Tasks créés pendant le sprint
            $io->section('Test 2: Bug Tasks créés pendant le sprint');
            $jql2 = sprintf(
                'project = "MD" AND issuetype IN subTaskIssueTypes() AND created >= "%s" AND created <= "%s"',
                $currentSprint->getStartDate()->format('Y-m-d'),
                $currentSprint->getEndDate()->format('Y-m-d')
            );
            $io->text("JQL: $jql2");
            
            try {
                $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', [
                    'query' => [
                        'jql' => $jql2,
                        'maxResults' => 5,
                        'fields' => 'key,summary,status,issuetype'
                    ]
                ]);
                $data = $response->toArray();
                $io->text("Total Bug Tasks: " . ($data['total'] ?? 'N/A'));
                
                if (!empty($data['issues'])) {
                    $io->text("Exemples de Bug Tasks:");
                    foreach (array_slice($data['issues'], 0, 3) as $issue) {
                        $fields = $issue['fields'] ?? [];
                        $io->text("- {$issue['key']}: {$fields['summary']} (Status: {$fields['status']['name']})");
                    }
                }
            } catch (\Exception $e) {
                $io->error("Erreur: " . $e->getMessage());
            }

            // Test 3: Tickets par statut
            $io->section('Test 3: Tickets par statut');
            $statuses = ['To Do', 'In Progress', 'Dev terminé', 'Devs terminés', 'Test PO', 'En attente', 'Finalisé', 'Terminé'];
            
            foreach ($statuses as $status) {
                $jql = sprintf('project = "MD" AND sprint = %d AND status = "%s"', $currentSprint->getJiraId(), $status);
                
                try {
                    $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', [
                        'query' => [
                            'jql' => $jql,
                            'maxResults' => 1,
                            'fields' => 'key'
                        ]
                    ]);
                    $data = $response->toArray();
                    $count = $data['total'] ?? 0;
                    $io->text("$status: $count tickets");
                } catch (\Exception $e) {
                    $io->text("$status: Erreur - " . $e->getMessage());
                }
            }

            // Test 4: Vérifier les statuts réels
            $io->section('Test 4: Statuts réels des tickets du sprint');
            $jql4 = sprintf('project = "MD" AND sprint = %d', $currentSprint->getJiraId());
            
            try {
                $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', [
                    'query' => [
                        'jql' => $jql4,
                        'maxResults' => 20,
                        'fields' => 'key,status'
                    ]
                ]);
                $data = $response->toArray();
                
                $statusCounts = [];
                foreach ($data['issues'] ?? [] as $issue) {
                    $status = $issue['fields']['status']['name'] ?? 'Unknown';
                    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
                }
                
                $io->text("Statuts réels trouvés:");
                foreach ($statusCounts as $status => $count) {
                    $io->text("- $status: $count tickets");
                }
                
            } catch (\Exception $e) {
                $io->error("Erreur: " . $e->getMessage());
            }

        } catch (\Exception $e) {
            $io->error('Erreur générale: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
