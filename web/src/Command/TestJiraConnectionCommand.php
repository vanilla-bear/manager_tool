<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:test:jira-connection',
    description: 'Test Jira connection',
)]
class TestJiraConnectionCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $jiraClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔗 Test Jira Connection');

        try {
            // Test simple : récupérer les projets
            $io->text('Test 1: Récupération des projets...');
            $response = $this->jiraClient->request('GET', '/rest/api/3/project');
            $data = $response->toArray();
            $io->success('✅ Connexion réussie !');
            $io->text('Projets trouvés: ' . count($data));
            
            foreach (array_slice($data, 0, 3) as $project) {
                $io->text("- {$project['key']}: {$project['name']}");
            }

            // Test 2: Recherche simple
            $io->text('Test 2: Recherche simple...');
            $response = $this->jiraClient->request('GET', '/rest/api/3/search', [
                'query' => [
                    'jql' => 'project = MD',
                    'maxResults' => 5,
                    'fields' => 'key,summary'
                ]
            ]);
            $data = $response->toArray();
            $io->success('✅ Recherche réussie !');
            $io->text('Tickets trouvés: ' . ($data['total'] ?? 0));
            
            if (!empty($data['issues'])) {
                foreach (array_slice($data['issues'], 0, 3) as $issue) {
                    $fields = $issue['fields'] ?? [];
                    $io->text("- {$issue['key']}: {$fields['summary']}");
                }
            }

        } catch (\Exception $e) {
            $io->error('❌ Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
