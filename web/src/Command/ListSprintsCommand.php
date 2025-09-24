<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:list-sprints',
    description: 'Liste tous les sprints du tableau Jira',
)]
class ListSprintsCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly string $jiraBoardId,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'state',
                null,
                InputOption::VALUE_OPTIONAL,
                'Filtrer par état (active, closed, future)',
                'all'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Limiter le nombre de résultats',
                50
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $state = $input->getOption('state');
        $limit = (int) $input->getOption('limit');

        $io->title('Liste des sprints Jira');
        $io->section(sprintf('Tableau ID: %s', $this->jiraBoardId));

        try {
            $sprints = $this->fetchSprints($state, $limit);
            
            if (empty($sprints)) {
                $io->warning('Aucun sprint trouvé');
                return Command::SUCCESS;
            }

            $io->success(sprintf('%d sprints trouvés', count($sprints)));

            // Afficher un tableau récapitulatif
            $sprintData = [];
            foreach ($sprints as $sprint) {
                $sprintData[] = [
                    $sprint['id'],
                    $sprint['name'],
                    $sprint['state'] ?? 'N/A',
                    $sprint['startDate'] ?? 'N/A',
                    $sprint['endDate'] ?? 'N/A',
                    $sprint['goal'] ?? 'N/A',
                ];
            }

            $io->table(
                ['ID', 'Nom', 'État', 'Début', 'Fin', 'Objectif'],
                $sprintData
            );

            // Afficher les URLs pour accéder aux sprints
            $io->section('URLs d\'accès aux sprints:');
            foreach ($sprints as $sprint) {
                $url = sprintf(
                    'https://studi-pedago.atlassian.net/jira/software/projects/MD/boards/%s?sprint=%d',
                    $this->jiraBoardId,
                    $sprint['id']
                );
                $io->text(sprintf('• %s: %s', $sprint['name'], $url));
            }

        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la récupération des sprints : %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function fetchSprints(string $state, int $limit): array
    {
        $allSprints = [];
        $startAt = 0;
        $maxResults = 50; // Récupérer par pages de 50
        
        while (count($allSprints) < $limit) {
            $query = [
                'maxResults' => $maxResults,
                'startAt' => $startAt,
            ];

            if ($state !== 'all') {
                $query['state'] = $state;
            }

            $response = $this->jiraClient->request('GET', "/rest/agile/1.0/board/{$this->jiraBoardId}/sprint", [
                'query' => $query
            ]);

            $data = $response->toArray();
            $sprints = $data['values'] ?? [];
            
            if (empty($sprints)) {
                break; // Plus de sprints disponibles
            }
            
            $allSprints = array_merge($allSprints, $sprints);
            $startAt += $maxResults;
            
            // Arrêter si on a atteint la limite ou s'il n'y a plus de sprints
            if (count($sprints) < $maxResults) {
                break;
            }
        }
        
        // Limiter au nombre demandé
        $allSprints = array_slice($allSprints, 0, $limit);
        
        // Trier par date de début (les plus récents en premier)
        usort($allSprints, function($a, $b) {
            $dateA = isset($a['startDate']) ? strtotime($a['startDate']) : 0;
            $dateB = isset($b['startDate']) ? strtotime($b['startDate']) : 0;
            return $dateB - $dateA; // Ordre décroissant (plus récent en premier)
        });
        
        return $allSprints;
    }
}
