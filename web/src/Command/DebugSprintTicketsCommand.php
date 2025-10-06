<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:debug:sprint-tickets',
    description: 'Debug tickets for a specific sprint',
)]
class DebugSprintTicketsCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $jiraClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('sprint-id', InputArgument::REQUIRED, 'Sprint ID to debug')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sprintId = $input->getArgument('sprint-id');

        $io->title("🔍 Debug Sprint Tickets (ID: $sprintId)");

        // Test 1: Votre requête JQL exacte
        $io->section("📋 Test 1: Votre requête JQL");
        $jql1 = "project = MD AND sprint = $sprintId AND type IN standardIssueTypes() ORDER BY cf[10200] ASC, created DESC";
        $io->text("Query: $jql1");
        
        try {
            $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', [
                'query' => [
                    'jql' => $jql1,
                    'maxResults' => 200,
                    'fields' => 'key,summary,status,customfield_10200,created,issuetype'
                ]
            ]);
            
            $data = $response->toArray();
            $issues = $data['issues'] ?? [];
            
            $io->success("✅ Tickets trouvés: " . count($issues));
            
            $totalPoints = 0;
            $ticketsWithPoints = 0;
            $ticketsWithoutPoints = 0;
            
            $io->text("\n📊 Détail des tickets:");
            $io->text("Key\t\tStory Points\tType\t\tStatus");
            $io->text("---\t\t------------\t----\t\t------");
            
            foreach ($issues as $issue) {
                $key = $issue['key'];
                $status = $issue['fields']['status']['name'] ?? '';
                $issuetype = $issue['fields']['issuetype']['name'] ?? '';
                $storyPoints = $issue['fields']['customfield_10200'] ?? null;
                
                if ($storyPoints !== null) {
                    $totalPoints += (float)$storyPoints;
                    $ticketsWithPoints++;
                } else {
                    $ticketsWithoutPoints++;
                }
                
                $io->text(sprintf("%-12s\t%s\t\t%-12s\t%s", 
                    $key, 
                    $storyPoints !== null ? $storyPoints : 'N/A',
                    $issuetype,
                    $status
                ));
            }
            
            $io->text("\n📈 Résumé:");
            $io->text("- Total tickets: " . count($issues));
            $io->text("- Tickets avec story points: $ticketsWithPoints");
            $io->text("- Tickets sans story points: $ticketsWithoutPoints");
            $io->text("- Total story points: $totalPoints");
            
        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
        }

        $io->newLine();
        $io->text(str_repeat("=", 50));

        // Test 2: Requête actuelle du code
        $io->section("📋 Test 2: Requête actuelle du code");
        $jql2 = "project = MD AND sprint = $sprintId AND issuetype in (Bug, Epic, Story, Task, Technique) ORDER BY created ASC";
        $io->text("Query: $jql2");
        
        try {
            $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', [
                'query' => [
                    'jql' => $jql2,
                    'maxResults' => 200,
                    'fields' => 'key,summary,status,customfield_10200,created,issuetype'
                ]
            ]);
            
            $data = $response->toArray();
            $issues = $data['issues'] ?? [];
            
            $io->success("✅ Tickets trouvés: " . count($issues));
            
            $totalPoints = 0;
            foreach ($issues as $issue) {
                $storyPoints = $issue['fields']['customfield_10200'] ?? null;
                if ($storyPoints !== null) {
                    $totalPoints += (float)$storyPoints;
                }
            }
            
            $io->text("- Total story points: $totalPoints");
            
        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
