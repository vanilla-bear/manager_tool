<?php

namespace App\Service;

use App\Repository\SprintRepository;
use Psr\Log\LoggerInterface;

class SimpleVelocityPredictionService
{
    public function __construct(
        private readonly SprintRepository $sprintRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Prédiction simple basée sur vos données réelles
     */
    public function predictNextSprintVelocity(): array
    {
        $this->logger->info('Starting simple velocity prediction');
        
        $recentSprints = $this->sprintRepository->findLastSprints(10);
        
        if (count($recentSprints) < 3) {
            return [
                'predicted_velocity' => 0,
                'confidence' => 0,
                'message' => 'Pas assez de données historiques pour une prédiction fiable'
            ];
        }

        // Utiliser les points terminés (completedPoints) comme dans vos données
        $historicalVelocities = array_map(function($sprint) {
            return $sprint->getCompletedPoints();
        }, $recentSprints);

        $sprintDetails = array_map(function($sprint) {
            return [
                'name' => $sprint->getName(),
                'jira_id' => $sprint->getJiraId(),
                'start_date' => $sprint->getStartDate() ? $sprint->getStartDate()->format('Y-m-d') : null,
                'end_date' => $sprint->getEndDate() ? $sprint->getEndDate()->format('Y-m-d') : null,
                'completed_points' => $sprint->getCompletedPoints(),
                'committed_points' => $sprint->getCommittedPoints(),
                'added_points' => $sprint->getAddedPointsDuringSprint()
            ];
        }, $recentSprints);

        $this->logger->info('Historical velocities with sprint details', [
            'velocities' => $historicalVelocities,
            'sprint_details' => $sprintDetails
        ]);

        // Calcul simple basé sur vos données réelles
        $averageVelocity = array_sum($historicalVelocities) / count($historicalVelocities);
        
        // Tendance simple : comparaison des 3 derniers vs les précédents
        $trend = 0;
        if (count($historicalVelocities) >= 6) {
            $last3 = array_slice($historicalVelocities, 0, 3);
            $previous3 = array_slice($historicalVelocities, 3, 3);
            $trend = (array_sum($last3) / count($last3)) - (array_sum($previous3) / count($previous3));
        }

        // Prédiction basée sur la moyenne avec ajustement de tendance
        $predictedVelocity = $averageVelocity + ($trend * 0.5);
        
        // Limiter la prédiction à une fourchette raisonnable
        $minVelocity = $averageVelocity * 0.8;
        $maxVelocity = $averageVelocity * 1.2;
        $predictedVelocity = max($minVelocity, min($maxVelocity, $predictedVelocity));

        // Confiance basée sur la stabilité des données
        $variance = $this->calculateVariance($historicalVelocities);
        $confidence = max(0.3, min(0.9, 1 - ($variance / $averageVelocity)));

        // Facteurs de risque basés sur vos patterns
        $riskFactors = $this->identifyRiskFactors($recentSprints);

        return [
            'predicted_velocity' => round($predictedVelocity, 1),
            'confidence' => round($confidence, 2),
            'trend' => round($trend, 1),
            'seasonality' => 0, // Simplifié pour l'instant
            'team_capacity' => 0, // Simplifié pour l'instant
            'risk_factors' => $riskFactors,
            'historical_average' => round($averageVelocity, 1),
            'recommendations' => $this->generateRecommendations($predictedVelocity, $confidence, $riskFactors),
            'sprint_details' => $sprintDetails,
            'debug_info' => [
                'velocities' => $historicalVelocities,
                'average' => $averageVelocity,
                'trend' => $trend,
                'variance' => $variance
            ]
        ];
    }

    /**
     * Calcule la variance des vélocités
     */
    private function calculateVariance(array $velocities): float
    {
        if (count($velocities) < 2) {
            return 0;
        }

        $mean = array_sum($velocities) / count($velocities);
        $variance = array_sum(array_map(function($v) use ($mean) {
            return pow($v - $mean, 2);
        }, $velocities)) / count($velocities);

        return sqrt($variance);
    }

    /**
     * Identifie les facteurs de risque basés sur vos données
     */
    private function identifyRiskFactors(array $sprints): array
    {
        $riskFactors = [];

        // Analyser les points ajoutés pendant le sprint
        $sprintsWithScopeCreep = array_filter($sprints, function($sprint) {
            return $sprint->getAddedPointsDuringSprint() > 0;
        });

        if (count($sprintsWithScopeCreep) / count($sprints) > 0.6) {
            $riskFactors[] = [
                'type' => 'scope_creep',
                'severity' => 'medium',
                'description' => 'Tendance à ajouter des points pendant le sprint',
                'impact' => 'Risque de surcharge et de retard'
            ];
        }

        // Analyser la différence entre engagé et terminé
        $underDelivery = array_filter($sprints, function($sprint) {
            return $sprint->getCompletedPoints() < ($sprint->getCommittedPoints() * 0.9);
        });

        if (count($underDelivery) / count($sprints) > 0.4) {
            $riskFactors[] = [
                'type' => 'under_delivery',
                'severity' => 'high',
                'description' => 'Livraison souvent inférieure aux engagements',
                'impact' => 'Risque de non-atteinte des objectifs'
            ];
        }

        return $riskFactors;
    }

    /**
     * Génère des recommandations simples
     */
    private function generateRecommendations(float $predictedVelocity, float $confidence, array $riskFactors): array
    {
        $recommendations = [];

        if ($confidence < 0.6) {
            $recommendations[] = [
                'type' => 'data_quality',
                'priority' => 'high',
                'message' => 'Améliorer la stabilité des estimations pour des prédictions plus fiables'
            ];
        }

        if ($predictedVelocity < 50) {
            $recommendations[] = [
                'type' => 'velocity',
                'priority' => 'medium',
                'message' => 'Considérer réduire la charge de travail du prochain sprint'
            ];
        }

        foreach ($riskFactors as $risk) {
            if ($risk['severity'] === 'high') {
                $recommendations[] = [
                    'type' => 'risk_mitigation',
                    'priority' => 'high',
                    'message' => "Atténuer le risque: {$risk['description']}"
                ];
            }
        }

        return $recommendations;
    }
}
