<?php

namespace App\Command;

use App\Service\SprintSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-jira',
    description: 'Synchronise les données depuis Jira',
)]
class SyncJiraDataCommand extends Command
{
    public function __construct(
        private readonly SprintSyncService $sprintSyncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'sprints',
                null,
                InputOption::VALUE_NONE,
                'Synchroniser les sprints'
            )
            ->addOption(
                'from',
                null,
                InputOption::VALUE_REQUIRED,
                'Date de début (format: Y-m-d)',
                (new \DateTime('-6 months'))->format('Y-m-d')
            )
            ->addOption(
                'to',
                null,
                InputOption::VALUE_REQUIRED,
                'Date de fin (format: Y-m-d)',
                (new \DateTime())->format('Y-m-d')
            )
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Synchroniser toutes les données'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startDate = new \DateTime($input->getOption('from'));
        $endDate = new \DateTime($input->getOption('to'));

        $io->title('Synchronisation des données Jira');
        $io->section(sprintf('Période : du %s au %s', $startDate->format('Y-m-d'), $endDate->format('Y-m-d')));

        $syncSprints = $input->getOption('sprints') || $input->getOption('all');

        if ($syncSprints) {
            try {
                $io->section('Synchronisation des sprints');
                
                // Créer une barre de progression
                $progressBar = $io->createProgressBar();
                $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
                
                $sprints = $this->sprintSyncService->synchronizeSprints(
                    $startDate,
                    $endDate,
                    function (int $current, int $total, string $sprintName) use ($progressBar) {
                        if ($current === 1) {
                            $progressBar->start($total);
                        }
                        $progressBar->setMessage("Sprint en cours : $sprintName");
                        $progressBar->setProgress($current);
                    }
                );
                
                $progressBar->finish();
                $io->newLine(2);
                
                $io->success(sprintf('%d sprints synchronisés', count($sprints)));
                
                // Afficher un tableau récapitulatif
                $sprintData = [];
                foreach ($sprints as $sprint) {
                    $sprintData[] = [
                        $sprint->getName(),
                        $sprint->getStartDate()->format('Y-m-d'),
                        $sprint->getEndDate()->format('Y-m-d'),
                        $sprint->getCommittedPoints(),
                        $sprint->getCompletedPoints(),
                        $sprint->getAddedPointsDuringSprint(),
                        sprintf('%.1f%%', $sprint->getCompletionRate() ?? 0),
                    ];
                }
                
                if (!empty($sprintData)) {
                    $io->table(
                        ['Sprint', 'Début', 'Fin', 'Points Engagés', 'Points Terminés', 'Points Ajoutés', 'Taux'],
                        $sprintData
                    );
                }
            } catch (\Exception $e) {
                $io->error(sprintf('Erreur lors de la synchronisation des sprints : %s', $e->getMessage()));
                return Command::FAILURE;
            }
        }

        // TODO: Ajouter d'autres synchronisations si nécessaire (bugs, etc.)

        return Command::SUCCESS;
    }
} 