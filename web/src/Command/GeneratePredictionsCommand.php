<?php

namespace App\Command;

use App\Entity\VelocityPrediction;
use App\Service\VelocityPredictionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-predictions',
    description: 'Generate velocity predictions and store them in the database'
)]
class GeneratePredictionsCommand extends Command
{
    public function __construct(
        private readonly VelocityPredictionService $predictionService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force generation even if recent prediction exists'
            )
            ->addOption(
                'cleanup',
                'c',
                InputOption::VALUE_NONE,
                'Clean up expired predictions'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');
        $cleanup = $input->getOption('cleanup');

        $io->title('Génération des Prédictions de Vélocité');

        // Nettoyage des prédictions expirées
        if ($cleanup) {
            $io->section('Nettoyage des prédictions expirées...');
            $this->cleanupExpiredPredictions($io);
        }

        try {
            // Génération de la prédiction de vélocité
            $io->section('Génération de la prédiction de vélocité...');
            $velocityPrediction = $this->predictionService->predictNextSprintVelocity();
            
            if (!$velocityPrediction || $velocityPrediction['predicted_velocity'] == 0) {
                $io->error('Impossible de générer une prédiction. Vérifiez que vous avez suffisamment de données historiques.');
                return Command::FAILURE;
            }

            // Génération de la prédiction de réussite
            $io->section('Génération de la prédiction de réussite...');
            $completionPrediction = $this->predictionService->predictSprintCompletion();

            // Vérification si une prédiction récente existe déjà
            if (!$force && $this->hasRecentPrediction()) {
                $io->warning('Une prédiction récente existe déjà. Utilisez --force pour forcer la génération.');
                return Command::SUCCESS;
            }

            // Création de l'entité de prédiction
            $prediction = new VelocityPrediction();
            $prediction->setPredictedVelocity($velocityPrediction['predicted_velocity']);
            $prediction->setConfidence($velocityPrediction['confidence']);
            $prediction->setTrend($velocityPrediction['trend']);
            $prediction->setSeasonality($velocityPrediction['seasonality']);
            $prediction->setTeamCapacity($velocityPrediction['team_capacity']);
            $prediction->setHistoricalAverage($velocityPrediction['historical_average']);
            $prediction->setCompletionProbability($completionPrediction['completion_probability'] / 100);
            $prediction->setRiskFactors($velocityPrediction['risk_factors']);
            $prediction->setRecommendations($velocityPrediction['recommendations']);

            // Calcul des dates du prochain sprint (approximation)
            $nextSprintStart = new \DateTimeImmutable('next monday');
            $nextSprintEnd = $nextSprintStart->modify('+2 weeks -1 day');
            
            $prediction->setTargetSprintStart($nextSprintStart);
            $prediction->setTargetSprintEnd($nextSprintEnd);

            // Sauvegarde
            $this->entityManager->persist($prediction);
            $this->entityManager->flush();

            $io->success('Prédiction générée avec succès !');
            
            // Affichage des résultats
            $io->table(
                ['Métrique', 'Valeur'],
                [
                    ['Vélocité Prédite', $velocityPrediction['predicted_velocity']],
                    ['Confiance', round($velocityPrediction['confidence'] * 100, 1) . '%'],
                    ['Probabilité de Réussite', round($completionPrediction['completion_probability'], 1) . '%'],
                    ['Tendance', $velocityPrediction['trend']],
                    ['Saisonnalité', $velocityPrediction['seasonality']],
                    ['Capacité Équipe', $velocityPrediction['team_capacity']],
                    ['Facteurs de Risque', count($velocityPrediction['risk_factors'])],
                    ['Recommandations', count($velocityPrediction['recommendations'])]
                ]
            );

            // Affichage des facteurs de risque
            if (!empty($velocityPrediction['risk_factors'])) {
                $io->section('Facteurs de Risque Identifiés:');
                foreach ($velocityPrediction['risk_factors'] as $risk) {
                    $io->writeln(sprintf(
                        '• <fg=%s>%s</>: %s',
                        $risk['severity'] === 'high' ? 'red' : ($risk['severity'] === 'medium' ? 'yellow' : 'blue'),
                        strtoupper($risk['severity']),
                        $risk['description']
                    ));
                }
            }

            // Affichage des recommandations
            if (!empty($velocityPrediction['recommendations'])) {
                $io->section('Recommandations:');
                foreach ($velocityPrediction['recommendations'] as $recommendation) {
                    $io->writeln(sprintf(
                        '• <fg=%s>%s</>: %s',
                        $recommendation['priority'] === 'high' ? 'red' : ($recommendation['priority'] === 'medium' ? 'yellow' : 'blue'),
                        strtoupper($recommendation['priority']),
                        $recommendation['message']
                    ));
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors de la génération des prédictions: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Vérifie si une prédiction récente existe déjà
     */
    private function hasRecentPrediction(): bool
    {
        $repository = $this->entityManager->getRepository(VelocityPrediction::class);
        $recentPrediction = $repository->findLatestActive();
        
        if (!$recentPrediction) {
            return false;
        }

        // Considère comme récente si créée dans les dernières 24h
        $oneDayAgo = new \DateTimeImmutable('-1 day');
        return $recentPrediction->getPredictionDate() > $oneDayAgo;
    }

    /**
     * Nettoie les prédictions expirées
     */
    private function cleanupExpiredPredictions(SymfonyStyle $io): void
    {
        $repository = $this->entityManager->getRepository(VelocityPrediction::class);
        $expiredCount = $repository->deactivateExpired();
        
        if ($expiredCount > 0) {
            $io->success(sprintf('%d prédictions expirées ont été désactivées.', $expiredCount));
        } else {
            $io->info('Aucune prédiction expirée trouvée.');
        }
    }
}

