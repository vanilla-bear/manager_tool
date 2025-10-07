<?php

namespace App\Command;

use App\Controller\ThroughputController;
use App\Repository\SprintRepository;
use App\Service\ThroughputService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:throughput-controller',
    description: 'Test throughput controller logic',
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
        $io->title('🧪 Test Throughput Controller Logic');

        try {
            // Simuler la logique du controller
            $allSprints = $this->sprintRepository->findLastSprints(20);
            if (!empty($allSprints)) {
                $startDate = $allSprints[count($allSprints) - 1]->getStartDate()->setTime(0, 0, 0);
                $endDate = $allSprints[0]->getEndDate()->setTime(23, 59, 59);
            } else {
                $startDate = new \DateTime('2025-01-01');
                $endDate = new \DateTime('2025-12-31');
            }

            $io->text("Période calculée: {$startDate->format('Y-m-d')} à {$endDate->format('Y-m-d')}");

            // Tester le service
            $data = $this->throughputService->getThroughputData($startDate, $endDate);
            
            $io->success('✅ Controller logic fonctionne !');
            $io->text("Sprints: " . count($data['sprints']));
            $io->text("Averages: " . json_encode($data['averages']));
            $io->text("Summary: " . json_encode($data['summary']));

            // Vérifier que les clés attendues existent
            $requiredKeys = ['sprint', 'period', 'total', 'features', 'bugs', 'improvements', 'completed_points', 'devs_terminés', 'links'];
            if (!empty($data['sprints'])) {
                $firstSprint = $data['sprints'][0];
                $missingKeys = [];
                foreach ($requiredKeys as $key) {
                    if (!array_key_exists($key, $firstSprint)) {
                        $missingKeys[] = $key;
                    }
                }
                
                if (empty($missingKeys)) {
                    $io->success('✅ Toutes les clés requises sont présentes !');
                } else {
                    $io->error('❌ Clés manquantes: ' . implode(', ', $missingKeys));
                }
            }

        } catch (\Exception $e) {
            $io->error('❌ Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}