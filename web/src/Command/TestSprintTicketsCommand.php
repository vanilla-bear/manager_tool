<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'app:test:sprint-tickets',
    description: 'Test sprint tickets with direct connection',
)]
class TestSprintTicketsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🔗 Test Sprint Tickets');

        try {
            // Créer un client HTTP directement
            $client = HttpClient::create([
                'base_uri' => 'https://studi-pedago.atlassian.net',
                'headers' => [
                    'Authorization' => 'Basic cm9tYWluLmRhbHZlcm55QHN0dWRpLmZyOk9HSHRNdXRCR0NRWnhaMXdrM0RKQkVBMw==',
                    'Content-Type' => 'application/json',
                ]
            ]);

            // Récupérer le sprint actuel
            $io->text('Récupération du sprint actuel...');
            $response = $client->request('GET', '/rest/agile/1.0/board/1359/sprint', [
                'query' => [
                    'state' => 'active',
                    'maxResults' => 1
                ]
            ]);
            $data = $response->toArray();
            
            if (empty($data['values'])) {
                $io->warning('Aucun sprint actif trouvé');
                return Command::SUCCESS;
            }

            $sprint = $data['values'][0];
            $io->success("Sprint actuel: {$sprint['name']} (ID: {$sprint['id']})");
            $io->text("Période: {$sprint['startDate']} à {$sprint['endDate']}");

            // Récupérer les tickets du sprint via l'API Agile
            $io->text('Récupération des tickets du sprint...');
            $response = $client->request('GET', '/rest/agile/1.0/sprint/' . $sprint['id'] . '/issue', [
                'query' => [
                    'maxResults' => 50,
                    'fields' => 'key,summary,status,issuetype'
                ]
            ]);
            $data = $response->toArray();
            
            $io->success('✅ Tickets du sprint récupérés !');
            $io->text('Tickets trouvés: ' . ($data['total'] ?? 0));
            
            if (!empty($data['issues'])) {
                $statusCounts = [];
                $issueTypes = [];
                
                foreach ($data['issues'] as $issue) {
                    $status = $issue['fields']['status']['name'] ?? 'Unknown';
                    $issueType = $issue['fields']['issuetype']['name'] ?? 'Unknown';
                    
                    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
                    $issueTypes[$issueType] = ($issueTypes[$issueType] ?? 0) + 1;
                }
                
                $io->section('Statuts trouvés:');
                foreach ($statusCounts as $status => $count) {
                    $io->text("- $status: $count tickets");
                }
                
                $io->section('Types de tickets:');
                foreach ($issueTypes as $type => $count) {
                    $io->text("- $type: $count tickets");
                }
                
                // Compter les Bug Tasks
                $bugTasksCount = 0;
                foreach ($data['issues'] as $issue) {
                    $issueType = $issue['fields']['issuetype']['name'] ?? '';
                    if (strpos($issueType, 'Sub-task') !== false || strpos($issueType, 'Bug') !== false) {
                        $bugTasksCount++;
                    }
                }
                
                $io->section('Bug Tasks:');
                $io->text("Bug Tasks trouvés: $bugTasksCount");
            }

        } catch (\Exception $e) {
            $io->error('❌ Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
