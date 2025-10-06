<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:test:direct-jira',
    description: 'Test direct Jira connection',
)]
class TestDirectJiraCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔗 Test Direct Jira Connection');

        try {
            // Créer un client HTTP directement
            $client = HttpClient::create([
                'base_uri' => 'https://studi-pedago.atlassian.net',
                'headers' => [
                    'Authorization' => 'Basic cm9tYWluLmRhbHZlcm55QHN0dWRpLmZyOk9HSHRNdXRCR0NRWnhaMXdrM0RKQkVBMw==',
                    'Content-Type' => 'application/json',
                ]
            ]);

            // Test 1: Récupérer les projets
            $io->text('Test 1: Récupération des projets...');
            $response = $client->request('GET', '/rest/api/3/project');
            $data = $response->toArray();
            $io->success('✅ Connexion réussie !');
            $io->text('Projets trouvés: ' . count($data));
            
            foreach (array_slice($data, 0, 3) as $project) {
                $io->text("- {$project['key']}: {$project['name']}");
            }

            // Test 2: Recherche simple
            $io->text('Test 2: Recherche simple...');
            $response = $client->request('GET', '/rest/api/3/search', [
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

            // Test 3: Sprint actuel
            $io->text('Test 3: Sprint actuel...');
            $response = $client->request('GET', '/rest/agile/1.0/board/1359/sprint', [
                'query' => [
                    'state' => 'active',
                    'maxResults' => 1
                ]
            ]);
            $data = $response->toArray();
            $io->success('✅ Sprint récupéré !');
            
            if (!empty($data['values'])) {
                $sprint = $data['values'][0];
                $io->text("Sprint actuel: {$sprint['name']} (ID: {$sprint['id']})");
                
                // Test 4: Tickets du sprint
                $io->text('Test 4: Tickets du sprint...');
                $response = $client->request('GET', '/rest/api/3/search', [
                    'query' => [
                        'jql' => 'project = MD AND sprint = ' . $sprint['id'],
                        'maxResults' => 10,
                        'fields' => 'key,summary,status'
                    ]
                ]);
                $data = $response->toArray();
                $io->success('✅ Tickets du sprint récupérés !');
                $io->text('Tickets trouvés: ' . ($data['total'] ?? 0));
                
                if (!empty($data['issues'])) {
                    $statusCounts = [];
                    foreach ($data['issues'] as $issue) {
                        $status = $issue['fields']['status']['name'] ?? 'Unknown';
                        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
                    }
                    
                    $io->text('Statuts trouvés:');
                    foreach ($statusCounts as $status => $count) {
                        $io->text("- $status: $count tickets");
                    }
                }
            }

        } catch (\Exception $e) {
            $io->error('❌ Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
