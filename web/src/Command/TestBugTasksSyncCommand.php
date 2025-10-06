<?php

namespace App\Command;

use App\Service\BugStatsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:bug-tasks-sync',
    description: 'Test Bug Tasks synchronization',
)]
class TestBugTasksSyncCommand extends Command
{
    public function __construct(
        private readonly BugStatsService $bugStatsService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("🧪 Test Bug Tasks Synchronization");

        // Test avec le mois de septembre 2025
        $testDate = new \DateTime('2025-09-01');
        $io->text("Test de synchronisation pour: " . $testDate->format('F Y'));

        try {
            $stats = $this->bugStatsService->synchronizeMonth($testDate);
            
            $io->success("✅ Synchronisation réussie !");
            
            $io->section("📊 Résultats");
            $io->text("Mois: " . $stats->getMonth()->format('F Y'));
            $io->text("Bugs: " . $stats->getBugsCount());
            $io->text("Bug Tasks: " . $stats->getBugTasksCount());
            $io->text("Features Delivered: " . $stats->getDeliveredTicketsCount());
            $io->text("Bug Rate: " . ($stats->getBugRate() ? round($stats->getBugRate() * 100, 1) . '%' : 'N/A'));
            $io->text("Synchronisé le: " . $stats->getSyncedAt()->format('Y-m-d H:i:s'));
            
        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

