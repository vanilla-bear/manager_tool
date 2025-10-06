<?php

namespace App\Command;

use App\Repository\SprintRepository;
use App\Service\ThroughputService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:throughput-controller',
    description: 'Test the throughput controller logic',
)]
class TestThroughputControllerCommand extends Command
{
    public function __construct(
        private readonly ThroughputService $throughputService,
        private readonly SprintRepository $sprintRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("🧪 Test Throughput Controller Logic");

        // Simuler la logique du contrôleur
        $allSprints = $this->sprintRepository->findLastSprints(20);
        if (!empty($allSprints)) {
            $startDate = $allSprints[count($allSprints) - 1]->getStartDate()->setTime(0, 0, 0);
            $endDate = $allSprints[0]->getEndDate()->setTime(23, 59, 59);
        } else {
            $startDate = new \DateTime('2025-01-01');
            $endDate = new \DateTime('2025-12-31');
        }

        $io->text("Période calculée: {$startDate->format('Y-m-d')} à {$endDate->format('Y-m-d')}");

        try {
            $data = $this->throughputService->getThroughputData($startDate, $endDate);
            
            $io->success("✅ Contrôleur fonctionne correctement !");
            
            $io->section("📊 Résumé");
            $io->text("Sprints trouvés: " . count($data['sprints']));
            $io->text("Total complété: " . $data['summary']['total_completed']);
            $io->text("Moyenne par sprint: " . $data['averages']['total']);
            
            $io->section("📈 Détail des sprints");
            foreach ($data['sprints'] as $sprint) {
                $io->text("- {$sprint['sprint']} ({$sprint['period']}): {$sprint['total']} tickets, {$sprint['completed_points']} points");
            }
            
        } catch (\Exception $e) {
            $io->error("❌ Erreur: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

