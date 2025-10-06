<?php

namespace App\Service;

use App\Repository\SprintRepository;
use DateTime;
use DateTimeImmutable;

class ThroughputService
{
    public function __construct(
        private readonly SprintRepository $sprintRepository,
    ) {
    }

    public function getThroughputData(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        // Récupérer les sprints dans la période
        $sprints = $this->sprintRepository->findByDateRange($startDate, $endDate);
        
        $sprintData = [];
        $totalCompleted = 0;
        $totalFeatures = 0;
        $totalBugs = 0;
        $totalImprovements = 0;
        
        foreach ($sprints as $sprint) {
            // Calculer le throughput basé sur les points terminés
            // On utilise une estimation : 1 point = ~1 ticket (approximation)
            $completedTickets = (int) round($sprint->getCompletedPoints());
            
            // Répartition approximative basée sur les patterns typiques
            $features = (int) round($completedTickets * 0.6); // 60% features
            $bugs = (int) round($completedTickets * 0.25);     // 25% bugs
            $improvements = (int) round($completedTickets * 0.15); // 15% improvements
            
            $sprintData[] = [
                'sprint' => $sprint->getName(),
                'period' => $sprint->getStartDate()->format('Y-m-d') . ' to ' . $sprint->getEndDate()->format('Y-m-d'),
                'total' => $completedTickets,
                'features' => $features,
                'bugs' => $bugs,
                'improvements' => $improvements,
                'completed_points' => $sprint->getCompletedPoints(),
                'devs_terminés' => $sprint->getDevsTerminesCount(),
                'links' => [
                    'total' => sprintf(
                        'https://studi-pedago.atlassian.net/issues/?jql=project = MD AND sprint = %d AND status = Terminé ORDER BY resolved ASC',
                        $sprint->getJiraId()
                    ),
                    'features' => sprintf(
                        'https://studi-pedago.atlassian.net/issues/?jql=project = MD AND sprint = %d AND status = Terminé AND issuetype = Story ORDER BY resolved ASC',
                        $sprint->getJiraId()
                    ),
                    'bugs' => sprintf(
                        'https://studi-pedago.atlassian.net/issues/?jql=project = MD AND sprint = %d AND status = Terminé AND issuetype IN (Bug, "Bug task") ORDER BY resolved ASC',
                        $sprint->getJiraId()
                    ),
                    'improvements' => sprintf(
                        'https://studi-pedago.atlassian.net/issues/?jql=project = MD AND sprint = %d AND status = Terminé AND issuetype NOT IN (Story, Bug, "Bug task") ORDER BY resolved ASC',
                        $sprint->getJiraId()
                    ),
                ]
            ];
            
            $totalCompleted += $completedTickets;
            $totalFeatures += $features;
            $totalBugs += $bugs;
            $totalImprovements += $improvements;
        }

        // Calculer les moyennes par sprint
        $totalSprints = count($sprintData);
        $averages = [
            'total' => $totalSprints > 0 ? round($totalCompleted / $totalSprints, 1) : 0,
            'features' => $totalSprints > 0 ? round($totalFeatures / $totalSprints, 1) : 0,
            'bugs' => $totalSprints > 0 ? round($totalBugs / $totalSprints, 1) : 0,
            'improvements' => $totalSprints > 0 ? round($totalImprovements / $totalSprints, 1) : 0,
        ];

        return [
            'sprints' => $sprintData,
            'averages' => $averages,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'summary' => [
                'total_sprints' => $totalSprints,
                'total_completed' => $totalCompleted,
                'total_features' => $totalFeatures,
                'total_bugs' => $totalBugs,
                'total_improvements' => $totalImprovements,
            ]
        ];
    }

} 