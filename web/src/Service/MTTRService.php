<?php

namespace App\Service;

use DateTime;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class MTTRService
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getMTTRData(DateTime $startDate, DateTime $endDate): array
    {
        $jql = sprintf(
            'project = MD AND issuetype IN (Bug, Incident) AND created >= "%s" AND created <= "%s" AND status = Done ORDER BY created ASC',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
            'query' => [
                'jql' => $jql,
                'maxResults' => self::MAX_RESULTS_PER_PAGE,
                'fields' => 'key,summary,created,resolutiondate,status,issuetype'
            ]
        ]);

        $data = $response->toArray();
        $incidents = [];
        $monthlyMTTR = [];
        $totalResolutionTime = 0;
        $resolvedCount = 0;

        foreach ($data['issues'] as $incident) {
            if (!isset($incident['fields']['resolutiondate'])) {
                continue;
            }

            $createdDate = new DateTime($incident['fields']['created']);
            $resolvedDate = new DateTime($incident['fields']['resolutiondate']);
            $resolutionTime = $resolvedDate->diff($createdDate);
            $resolutionHours = ($resolutionTime->days * 24) + $resolutionTime->h + ($resolutionTime->i / 60);
            
            $monthKey = $createdDate->format('Y-m');

            if (!isset($monthlyMTTR[$monthKey])) {
                $monthlyMTTR[$monthKey] = [
                    'total_time' => 0,
                    'count' => 0,
                    'incidents' => []
                ];
            }

            $monthlyMTTR[$monthKey]['total_time'] += $resolutionHours;
            $monthlyMTTR[$monthKey]['count']++;
            $monthlyMTTR[$monthKey]['incidents'][] = [
                'key' => $incident['key'],
                'summary' => $incident['fields']['summary'],
                'type' => $incident['fields']['issuetype']['name'],
                'created' => $createdDate->format('Y-m-d H:i'),
                'resolved' => $resolvedDate->format('Y-m-d H:i'),
                'resolution_time' => round($resolutionHours, 1),
                'link' => sprintf('https://studi-pedago.atlassian.net/browse/%s', $incident['key'])
            ];

            $incidents[] = [
                'key' => $incident['key'],
                'summary' => $incident['fields']['summary'],
                'type' => $incident['fields']['issuetype']['name'],
                'created' => $createdDate->format('Y-m-d H:i'),
                'resolved' => $resolvedDate->format('Y-m-d H:i'),
                'resolution_time' => round($resolutionHours, 1),
                'link' => sprintf('https://studi-pedago.atlassian.net/browse/%s', $incident['key'])
            ];

            $totalResolutionTime += $resolutionHours;
            $resolvedCount++;
        }

        // Calculer le MTTR global
        $globalMTTR = $resolvedCount > 0 ? round($totalResolutionTime / $resolvedCount, 1) : 0;

        // Calculer le MTTR mensuel
        foreach ($monthlyMTTR as $month => $data) {
            $monthlyMTTR[$month]['mttr'] = $data['count'] > 0 ? round($data['total_time'] / $data['count'], 1) : 0;
        }

        return [
            'global_mttr' => $globalMTTR,
            'total_incidents' => count($incidents),
            'resolved_incidents' => $resolvedCount,
            'monthly_data' => array_values($monthlyMTTR),
            'incidents' => $incidents
        ];
    }

    /**
     * Récupère les bugs avec leurs données MTTR depuis Jira
     * 
     * @param DateTime $startDate Date de début
     * @param DateTime $endDate Date de fin
     * @param string|null $lastBugKey Clé du dernier bug synchronisé (pour la pagination)
     * @return array
     */
    public function getBugMTTRStats(DateTime $startDate, DateTime $endDate, ?string $lastBugKey = null): array
    {
        $bugs = [];
        $startAt = 0;
        $isLastPage = false;

        while (!$isLastPage) {
            // Construire la requête JQL
            $jql = sprintf(
                'project = MD AND issuetype = Bug AND created >= "%s" AND created <= "%s"',
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            // Si on a un dernier bug, on continue après lui
            if ($lastBugKey) {
                $jql .= sprintf(' AND key > "%s"', $lastBugKey);
            }

            $jql .= ' ORDER BY key ASC';

            try {
                $response = $this->jiraClient->request('GET', '/rest/api/2/search', [
                    'query' => [
                        'jql' => $jql,
                        'startAt' => $startAt,
                        'maxResults' => self::MAX_RESULTS_PER_PAGE,
                        'fields' => [
                            'key',
                            'summary',
                            'created',
                            'status',
                            'issuetype'
                        ]
                    ]
                ]);

                $data = $response->toArray();
                
                foreach ($data['issues'] as $issue) {
                    $bugData = [
                        'key' => $issue['key'],
                        'summary' => $issue['fields']['summary'],
                        'created' => $issue['fields']['created'],
                        'currentStatus' => $issue['fields']['status']['name'],
                        'mttrStats' => $this->calculateBugMTTRStats($issue['key'])
                    ];
                    
                    $bugs[] = $bugData;
                }

                // Vérifier si on a atteint la dernière page
                $total = $data['total'];
                $startAt += self::MAX_RESULTS_PER_PAGE;
                $isLastPage = $startAt >= $total;

            } catch (\Exception $e) {
                $this->logger->error('Error fetching bugs from Jira: ' . $e->getMessage());
                break;
            }
        }

        return $bugs;
    }

    /**
     * Calcule les statistiques MTTR pour un bug spécifique
     * 
     * @param string $bugKey Clé du bug
     * @return array|null
     */
    private function calculateBugMTTRStats(string $bugKey): ?array
    {
        try {
            // Récupérer l'historique des statuts du bug
            $response = $this->jiraClient->request('GET', "/rest/api/2/issue/{$bugKey}?expand=changelog");
            $issue = $response->toArray();

            $createdAt = new DateTime($issue['fields']['created']);
            $statusHistory = [];
            $currentStatus = $issue['fields']['status']['name'];

            // Analyser l'historique des changements
            if (isset($issue['changelog']['histories'])) {
                foreach ($issue['changelog']['histories'] as $history) {
                    foreach ($history['items'] as $item) {
                        if ($item['field'] === 'status') {
                            $statusHistory[] = [
                                'from' => $item['fromString'],
                                'to' => $item['toString'],
                                'date' => new DateTime($history['created'])
                            ];
                        }
                    }
                }
            }

            // Calculer les temps de transition
            $mttrStats = [
                'createdToTermine' => null,
                'aFaireToTermine' => null,
                'aFaireToDevsTermines' => null
            ];

            $termineDate = null;
            $aFaireDate = null;
            $devsTerminesDate = null;

            // Trouver les dates importantes
            foreach ($statusHistory as $change) {
                if ($change['to'] === 'Terminé') {
                    $termineDate = $change['date'];
                } elseif ($change['to'] === 'À faire') {
                    $aFaireDate = $change['date'];
                } elseif ($change['to'] === 'Devs terminés') {
                    $devsTerminesDate = $change['date'];
                }
            }

            // Calculer les temps
            if ($termineDate) {
                $mttrStats['createdToTermine'] = $termineDate->getTimestamp() - $createdAt->getTimestamp();
            }

            if ($termineDate && $aFaireDate) {
                $mttrStats['aFaireToTermine'] = $termineDate->getTimestamp() - $aFaireDate->getTimestamp();
            }

            if ($devsTerminesDate && $aFaireDate) {
                $mttrStats['aFaireToDevsTermines'] = $devsTerminesDate->getTimestamp() - $aFaireDate->getTimestamp();
            }

            return $mttrStats;

        } catch (\Exception $e) {
            $this->logger->error("Error calculating MTTR stats for bug {$bugKey}: " . $e->getMessage());
            return null;
        }
    }
} 