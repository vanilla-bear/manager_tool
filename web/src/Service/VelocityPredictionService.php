<?php

namespace App\Service;

use App\Entity\Sprint;
use App\Repository\SprintRepository;
use App\Repository\TeamMemberRepository;
use Psr\Log\LoggerInterface;
use DateTime;

class VelocityPredictionService
{
    private const MIN_SPRINTS_FOR_PREDICTION = 3;
    private const TREND_WEIGHT = 0.3;
    private const SEASONALITY_WEIGHT = 0.2;
    private const TEAM_CAPACITY_WEIGHT = 0.5;

    public function __construct(
        private readonly SprintRepository $sprintRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Prédit la vélocité du prochain sprint
     */
    public function predictNextSprintVelocity(): array
    {
        $this->logger->info('Starting velocity prediction for next sprint');
        
        $recentSprints = $this->sprintRepository->findLastSprints(10);
        
        if (count($recentSprints) < self::MIN_SPRINTS_FOR_PREDICTION) {
            return [
                'predicted_velocity' => 0,
                'confidence' => 0,
                'message' => 'Pas assez de données historiques pour une prédiction fiable'
            ];
        }

        $historicalVelocities = array_map(function($sprint) {
            return $sprint->getCompletedPoints();
        }, $recentSprints);

        $this->logger->info('Historical velocities found', [
            'velocities' => $historicalVelocities,
            'sprint_names' => array_map(function($sprint) {
                return $sprint->getName();
            }, $recentSprints)
        ]);

        // Calcul de la tendance
        $trend = $this->calculateTrend($historicalVelocities);
        
        // Calcul de la saisonnalité (patterns mensuels)
        $seasonality = $this->calculateSeasonality($recentSprints);
        
        // Calcul de la capacité de l'équipe
        $teamCapacity = $this->calculateTeamCapacity();
        
        // Prédiction basée sur la moyenne pondérée
        $averageVelocity = array_sum($historicalVelocities) / count($historicalVelocities);
        
        // Ajustement plus réaliste basé sur vos données
        $trendAdjustment = $trend * 0.1; // Réduction du poids de la tendance
        $seasonalityAdjustment = $seasonality * 0.05; // Réduction du poids de la saisonnalité
        
        $predictedVelocity = $averageVelocity + $trendAdjustment + $seasonalityAdjustment;
        
        // S'assurer que la prédiction reste dans une fourchette raisonnable
        $minVelocity = $averageVelocity * 0.7; // Minimum 70% de la moyenne
        $maxVelocity = $averageVelocity * 1.3; // Maximum 130% de la moyenne
        $predictedVelocity = max($minVelocity, min($maxVelocity, $predictedVelocity));

        // Calcul de la confiance basée sur la variance
        $confidence = $this->calculateConfidence($historicalVelocities);

        // Identification des facteurs de risque
        $riskFactors = $this->identifyRiskFactors($recentSprints);

        $result = [
            'predicted_velocity' => round($predictedVelocity, 1),
            'confidence' => round($confidence, 2),
            'trend' => round($trend, 1),
            'seasonality' => round($seasonality, 1),
            'team_capacity' => round($teamCapacity, 1),
            'risk_factors' => $riskFactors,
            'historical_average' => round($averageVelocity, 1),
            'recommendations' => $this->generateRecommendations($predictedVelocity, $confidence, $riskFactors)
        ];

        $this->logger->info('Prediction calculation completed', [
            'average_velocity' => $averageVelocity,
            'trend' => $trend,
            'trend_adjustment' => $trendAdjustment,
            'seasonality' => $seasonality,
            'seasonality_adjustment' => $seasonalityAdjustment,
            'predicted_velocity' => $predictedVelocity,
            'confidence' => $confidence
        ]);

        return $result;
    }

    /**
     * Prédit la probabilité de réussite du prochain sprint
     */
    public function predictSprintCompletion(): array
    {
        $prediction = $this->predictNextSprintVelocity();
        $predictedVelocity = $prediction['predicted_velocity'];
        $confidence = $prediction['confidence'];

        // Calcul de la probabilité de réussite basée sur l'historique
        $recentSprints = $this->sprintRepository->findLastSprints(5);
        $successRate = $this->calculateSuccessRate($recentSprints);

        // Facteurs qui influencent la réussite
        $completionFactors = [
            'velocity_prediction' => $predictedVelocity,
            'confidence' => $confidence,
            'historical_success_rate' => $successRate,
            'team_stability' => $this->calculateTeamStability(),
            'scope_creep_risk' => $this->calculateScopeCreepRisk($recentSprints)
        ];

        $overallProbability = ($confidence * 0.4) + ($successRate * 0.3) + 
                            ($completionFactors['team_stability'] * 0.2) + 
                            ((1 - $completionFactors['scope_creep_risk']) * 0.1);

        return [
            'completion_probability' => round($overallProbability * 100, 1),
            'factors' => $completionFactors,
            'recommendations' => $this->generateCompletionRecommendations($overallProbability, $completionFactors)
        ];
    }

    /**
     * Identifie les facteurs de risque pour le prochain sprint
     */
    public function identifyRiskFactors(array $recentSprints = null): array
    {
        if (!$recentSprints) {
            $recentSprints = $this->sprintRepository->findLastSprints(5);
        }

        $riskFactors = [];

        // Analyse de la vélocité décroissante
        $velocities = array_map(function($sprint) {
            return $sprint->getCompletedPoints();
        }, $recentSprints);

        if (count($velocities) >= 3) {
            $trend = $this->calculateTrend($velocities);
            if ($trend < -5) {
                $riskFactors[] = [
                    'type' => 'velocity_decline',
                    'severity' => 'high',
                    'description' => 'Vélocité en baisse constante',
                    'impact' => 'Risque de non-atteinte des objectifs'
                ];
            }
        }

        // Analyse des points ajoutés pendant le sprint
        $scopeCreepSprints = array_filter($recentSprints, function($sprint) {
            return $sprint->getAddedPointsDuringSprint() > 0;
        });

        if (count($scopeCreepSprints) / count($recentSprints) > 0.6) {
            $riskFactors[] = [
                'type' => 'scope_creep',
                'severity' => 'medium',
                'description' => 'Tendance à ajouter des points pendant le sprint',
                'impact' => 'Risque de surcharge et de retard'
            ];
        }

        // Analyse de la capacité vs vélocité
        $capacityIssues = array_filter($recentSprints, function($sprint) {
            return $sprint->getPlannedCapacityDays() && 
                   $sprint->getCompletedPoints() < ($sprint->getPlannedCapacityDays() * 0.8);
        });

        if (count($capacityIssues) / count($recentSprints) > 0.4) {
            $riskFactors[] = [
                'type' => 'capacity_underutilization',
                'severity' => 'medium',
                'description' => 'Sous-utilisation de la capacité planifiée',
                'impact' => 'Optimisation possible de la planification'
            ];
        }

        return $riskFactors;
    }

    /**
     * Calcule la tendance des vélocités
     */
    private function calculateTrend(array $velocities): float
    {
        if (count($velocities) < 2) {
            return 0;
        }

        $n = count($velocities);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = $velocities[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        return $slope;
    }

    /**
     * Calcule la saisonnalité basée sur les patterns mensuels
     */
    private function calculateSeasonality(array $sprints): float
    {
        $monthlyAverages = [];
        
        foreach ($sprints as $sprint) {
            $month = $sprint->getStartDate()->format('Y-m');
            if (!isset($monthlyAverages[$month])) {
                $monthlyAverages[$month] = [];
            }
            $monthlyAverages[$month][] = $sprint->getCompletedPoints();
        }

        if (count($monthlyAverages) < 2) {
            return 0;
        }

        // Calcul de la variance saisonnière
        $currentMonth = (new DateTime())->format('Y-m');
        $currentMonthAvg = isset($monthlyAverages[$currentMonth]) ? 
                          array_sum($monthlyAverages[$currentMonth]) / count($monthlyAverages[$currentMonth]) : 0;
        
        $overallAvg = array_sum(array_map(function($month) {
            return array_sum($month) / count($month);
        }, $monthlyAverages)) / count($monthlyAverages);

        return $currentMonthAvg - $overallAvg;
    }

    /**
     * Calcule la capacité de l'équipe
     */
    private function calculateTeamCapacity(): float
    {
        $teamMembers = $this->teamMemberRepository->findAll();
        $totalCapacity = 0;

        foreach ($teamMembers as $member) {
            // Capacité basée sur les heures de travail par jour
            $totalCapacity += $member->getWorkingHoursPerDay();
        }

        return $totalCapacity;
    }

    /**
     * Calcule la confiance de la prédiction
     */
    private function calculateConfidence(array $velocities): float
    {
        if (count($velocities) < 2) {
            return 0;
        }

        $mean = array_sum($velocities) / count($velocities);
        $variance = array_sum(array_map(function($v) use ($mean) {
            return pow($v - $mean, 2);
        }, $velocities)) / count($velocities);

        $coefficientOfVariation = sqrt($variance) / $mean;
        
        // Plus le coefficient de variation est faible, plus la confiance est élevée
        return max(0, min(1, 1 - $coefficientOfVariation));
    }

    /**
     * Calcule le taux de réussite des sprints
     */
    private function calculateSuccessRate(array $sprints): float
    {
        if (empty($sprints)) {
            return 0;
        }

        $successfulSprints = 0;
        foreach ($sprints as $sprint) {
            // Un sprint est considéré comme réussi si la vélocité est >= 80% de la capacité planifiée
            if ($sprint->getPlannedCapacityDays() && 
                $sprint->getCompletedPoints() >= ($sprint->getPlannedCapacityDays() * 0.8)) {
                $successfulSprints++;
            }
        }

        return $successfulSprints / count($sprints);
    }

    /**
     * Calcule la stabilité de l'équipe
     */
    private function calculateTeamStability(): float
    {
        // Pour l'instant, on retourne une valeur fixe
        // Dans une version avancée, on pourrait analyser les changements d'équipe
        return 0.8;
    }

    /**
     * Calcule le risque de scope creep
     */
    private function calculateScopeCreepRisk(array $sprints): float
    {
        if (empty($sprints)) {
            return 0;
        }

        $sprintsWithScopeCreep = 0;
        foreach ($sprints as $sprint) {
            if ($sprint->getAddedPointsDuringSprint() > 0) {
                $sprintsWithScopeCreep++;
            }
        }

        return $sprintsWithScopeCreep / count($sprints);
    }

    /**
     * Génère des recommandations basées sur la prédiction
     */
    private function generateRecommendations(float $predictedVelocity, float $confidence, array $riskFactors): array
    {
        $recommendations = [];

        if ($confidence < 0.5) {
            $recommendations[] = [
                'type' => 'data_quality',
                'priority' => 'high',
                'message' => 'Améliorer la qualité des données historiques pour des prédictions plus fiables'
            ];
        }

        if ($predictedVelocity < 10) {
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

    /**
     * Génère des recommandations pour la réussite du sprint
     */
    private function generateCompletionRecommendations(float $probability, array $factors): array
    {
        $recommendations = [];

        if ($probability < 0.7) {
            $recommendations[] = [
                'type' => 'planning',
                'priority' => 'high',
                'message' => 'Réviser la planification du sprint pour améliorer les chances de réussite'
            ];
        }

        if ($factors['scope_creep_risk'] > 0.5) {
            $recommendations[] = [
                'type' => 'scope_control',
                'priority' => 'medium',
                'message' => 'Renforcer le contrôle du scope pour éviter les ajouts en cours de sprint'
            ];
        }

        if ($factors['team_stability'] < 0.7) {
            $recommendations[] = [
                'type' => 'team_stability',
                'priority' => 'medium',
                'message' => 'Travailler sur la stabilité de l\'équipe'
            ];
        }

        return $recommendations;
    }
}
