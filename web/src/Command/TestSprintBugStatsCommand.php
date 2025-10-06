<?php

namespace App\Command;

use App\Service\BugStatsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:sprint-bug-stats',
    description: 'Test Sprint Bug Stats',
)]
class TestSprintBugStatsCommand extends Command
{
    public function __construct(
        private readonly BugStatsService $bugStatsService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("🧪 Test Sprint Bug Stats");

        // Test avec une période large
        $startDate = new \DateTime('2025-01-01');
        $endDate = new \DateTime('2025-12-31');

        $io->text("Période de test: {$startDate->format('Y-m-d')} à {$endDate->format('Y-m-d')}");

        try {
            $stats = $this->bugStatsService->getSprintBugStats($startDate, $endDate);
            
            $io->success("✅ Service fonctionne correctement !");
            
            $io->section("📊 Résumé");
            $io->text("Sprints trouvés: " . count($stats['sprints']));
            $io->text("Total bugs: " . $stats['totals']['bugs']);
            $io->text("Total Bug Tasks: " . $stats['totals']['bug_tasks']);
            $io->text("Total delivered: " . $stats['totals']['delivered']);
            $io->text("Bug rate global: " . $stats['totals']['bug_rate'] . "%");
            
            $io->section("📈 Moyennes par sprint");
            $io->text("Bugs par sprint: " . $stats['averages']['bugs_per_sprint']);
            $io->text("Bug Tasks par sprint: " . $stats['averages']['bug_tasks_per_sprint']);
            $io->text("Delivered par sprint: " . $stats['averages']['delivered_per_sprint']);
            
            $io->section("📋 Détail des sprints");
            $table = $io->createTable();
            $table->setHeaders(['Sprint', 'Période', 'Bugs', 'Bug Tasks', 'Delivered', 'Bug Rate']);
            
            foreach ($stats['sprints'] as $sprintStat) {
                $sprint = $sprintStat['sprint'];
                $table->addRow([
                    $sprint->getName(),
                    $sprint->getStartDate()->format('Y-m-d') . ' to ' . $sprint->getEndDate()->format('Y-m-d'),
                    $sprintStat['bugs_count'],
                    $sprintStat['bug_tasks_count'],
                    $sprintStat['delivered_count'],
                    $sprintStat['bug_rate'] . '%'
                ]);
            }
            
            $table->render();
            
        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

