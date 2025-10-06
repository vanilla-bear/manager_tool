<?php

namespace App\Command;

use App\Entity\VelocityPrediction;
use App\Repository\VelocityPredictionRepository;
use App\Repository\SprintRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-prediction-accuracy',
    description: 'Update prediction accuracy with actual sprint results'
)]
class UpdatePredictionAccuracyCommand extends Command
{
    public function __construct(
        private readonly VelocityPredictionRepository $predictionRepository,
        private readonly SprintRepository $sprintRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'sprint-id',
                's',
                InputOption::VALUE_REQUIRED,
                'Specific sprint ID to update'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Update all pending predictions'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sprintId = $input->getOption('sprint-id');
        $updateAll = $input->getOption('all');

        $io->title('Mise à jour de la Précision des Prédictions');

        try {
            if ($sprintId) {
                $this->updateSpecificSprint($io, (int) $sprintId);
            } elseif ($updateAll) {
                $this->updateAllPendingPredictions($io);
            } else {
                $io->error('Veuillez spécifier --sprint-id ou --all');
                return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de la mise à jour: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Met à jour la précision pour un sprint spécifique
     */
    private function updateSpecificSprint(SymfonyStyle $io, int $sprintId): void
    {
        $sprint = $this->sprintRepository->find($sprintId);
        
        if (!$sprint) {
            $io->error(sprintf('Sprint avec l\'ID %d non trouvé.', $sprintId));
            return;
        }

        $io->section(sprintf('Mise à jour pour le sprint: %s', $sprint->getName()));

        // Trouve les prédictions qui correspondent à ce sprint
        $predictions = $this->findPredictionsForSprint($sprint);
        
        if (empty($predictions)) {
            $io->warning('Aucune prédiction trouvée pour ce sprint.');
            return;
        }

        $actualVelocity = $sprint->getCompletedPoints();
        
        foreach ($predictions as $prediction) {
            $this->updatePredictionAccuracy($prediction, $actualVelocity);
            $io->writeln(sprintf(
                'Prédiction ID %d: %s → %s (Précision: %.1f%%)',
                $prediction->getId(),
                $prediction->getPredictedVelocity(),
                $actualVelocity,
                $prediction->getAccuracy() * 100
            ));
        }

        $this->entityManager->flush();
        $io->success(sprintf('Précision mise à jour pour %d prédiction(s).', count($predictions)));
    }

    /**
     * Met à jour toutes les prédictions en attente
     */
    private function updateAllPendingPredictions(SymfonyStyle $io): void
    {
        $io->section('Mise à jour de toutes les prédictions en attente...');

        // Trouve les prédictions qui n'ont pas encore de vélocité réelle
        $pendingPredictions = $this->predictionRepository->createQueryBuilder('vp')
            ->where('vp.actualVelocity IS NULL')
            ->andWhere('vp.targetSprintEnd < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();

        if (empty($pendingPredictions)) {
            $io->info('Aucune prédiction en attente trouvée.');
            return;
        }

        $updatedCount = 0;

        foreach ($pendingPredictions as $prediction) {
            $sprint = $this->findSprintForPrediction($prediction);
            
            if ($sprint) {
                $actualVelocity = $sprint->getCompletedPoints();
                $this->updatePredictionAccuracy($prediction, $actualVelocity);
                $updatedCount++;
                
                $io->writeln(sprintf(
                    'Sprint %s: Prédiction %s → Réalité %s (Précision: %.1f%%)',
                    $sprint->getName(),
                    $prediction->getPredictedVelocity(),
                    $actualVelocity,
                    $prediction->getAccuracy() * 100
                ));
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d prédictions mises à jour.', $updatedCount));
    }

    /**
     * Trouve les prédictions qui correspondent à un sprint
     */
    private function findPredictionsForSprint($sprint): array
    {
        $sprintStart = $sprint->getStartDate();
        $sprintEnd = $sprint->getEndDate();

        return $this->predictionRepository->createQueryBuilder('vp')
            ->where('vp.targetSprintStart <= :sprintStart')
            ->andWhere('vp.targetSprintEnd >= :sprintEnd')
            ->setParameter('sprintStart', $sprintStart)
            ->setParameter('sprintEnd', $sprintEnd)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le sprint correspondant à une prédiction
     */
    private function findSprintForPrediction(VelocityPrediction $prediction): ?object
    {
        $targetStart = $prediction->getTargetSprintStart();
        $targetEnd = $prediction->getTargetSprintEnd();

        if (!$targetStart || !$targetEnd) {
            return null;
        }

        return $this->sprintRepository->createQueryBuilder('s')
            ->where('s.startDate <= :targetStart')
            ->andWhere('s.endDate >= :targetEnd')
            ->setParameter('targetStart', $targetStart)
            ->setParameter('targetEnd', $targetEnd)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Met à jour la précision d'une prédiction
     */
    private function updatePredictionAccuracy(VelocityPrediction $prediction, float $actualVelocity): void
    {
        $prediction->setActualVelocity($actualVelocity);
        $prediction->calculateAccuracy();
    }
}

