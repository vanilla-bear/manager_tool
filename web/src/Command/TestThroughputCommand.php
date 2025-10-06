<?php

namespace App\Command;

use App\Service\ThroughputService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:throughput',
    description: 'Test the new throughput service with sprint data',
)]
class TestThroughputCommand extends Command
{
    public function __construct(
        private readonly ThroughputService $throughputService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("🧪 Test Throughput Service");

        // Test avec les 3 derniers mois
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-3 months');

        $io->text("Période de test: {$startDate->format('Y-m-d')} à {$endDate->format('Y-m-d')}");

        try {
            $data = $this->throughputService->getThroughputData($startDate, $endDate);
            
            $io->success("✅ Service fonctionne correctement !");
            
            $io->section("📊 Résumé");
            $io->text("Sprints trouvés: " . count($data['sprints']));
            $io->text("Total complété: " . $data['summary']['total_completed']);
            $io->text("Moyenne par sprint: " . $data['averages']['total']);
            
            $io->section("📈 Détail des sprints");
            $table = $io->createTable();
            $table->setHeaders(['Sprint', 'Période', 'Total', 'Features', 'Bugs', 'Améliorations', 'Points']);
            
            foreach ($data['sprints'] as $sprint) {
                $table->addRow([
                    $sprint['sprint'],
                    $sprint['period'],
                    $sprint['total'],
                    $sprint['features'],
                    $sprint['bugs'],
                    $sprint['improvements'],
                    $sprint['completed_points']
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

