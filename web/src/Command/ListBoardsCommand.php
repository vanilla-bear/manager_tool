<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:list-boards',
    description: 'Liste tous les tableaux Jira disponibles',
)]
class ListBoardsCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $jiraClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Liste des tableaux Jira');

        try {
            $boards = $this->fetchBoards();
            
            if (empty($boards)) {
                $io->warning('Aucun tableau trouvé');
                return Command::SUCCESS;
            }

            $io->success(sprintf('%d tableaux trouvés', count($boards)));

            // Afficher un tableau récapitulatif
            $boardData = [];
            foreach ($boards as $board) {
                $boardData[] = [
                    $board['id'],
                    $board['name'],
                    $board['type'],
                    $board['location']['projectName'] ?? 'N/A',
                    $board['location']['projectKey'] ?? 'N/A',
                ];
            }

            $io->table(
                ['ID', 'Nom', 'Type', 'Projet', 'Clé'],
                $boardData
            );

            // Afficher les URLs pour accéder aux tableaux
            $io->section('URLs d\'accès aux tableaux:');
            foreach ($boards as $board) {
                $url = sprintf(
                    'https://studi-pedago.atlassian.net/jira/software/projects/%s/boards/%d',
                    $board['location']['projectKey'] ?? 'MD',
                    $board['id']
                );
                $io->text(sprintf('• %s: %s', $board['name'], $url));
            }

        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la récupération des tableaux : %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function fetchBoards(): array
    {
        $response = $this->jiraClient->request('GET', '/rest/agile/1.0/board', [
            'query' => [
                'maxResults' => 100,
            ]
        ]);

        $data = $response->toArray();
        return $data['values'] ?? [];
    }
}
