<?php

namespace App\Service;

use DateTime;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class BlockersService
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly string $jiraBoardId,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getBlockersData(DateTime $startDate, DateTime $endDate): array
    {
        $blockers = $this->fetchBlockers($startDate, $endDate);
    
        $monthlyData = [];
        $currentDate = clone $startDate;
    
        while ($currentDate <= $endDate) {
            $monthKey = $currentDate->format('Y-m');
            $monthlyData[$monthKey] = [
                'count' => 0,
                'totalDuration' => 0,
                'month' => $currentDate->format('F Y'),
                'blockers' => [],
                'link' => sprintf(
                    'https://studi-pedago.atlassian.net/issues/?jql=project = MD'
                )
            ];
            $currentDate->modify('+1 month');
        }
    
        foreach ($blockers as $blocker) {
            //dd($blocker);
            // Chercher le dernier changement du champ "indicateur" dans le changelog
            $lastIndicateurChangeDate = null;
    
            if (!empty($blocker['changelog']['histories'])) {
                foreach ($blocker['changelog']['histories'] as $history) {
                    if (!isset($history['items'])) continue;

                    // if(!isset($history['author']['accountId'])) continue;
                    // Micka 712020:0b7c7ee4-397f-4ba4-bffe-01e221de8146
                    // Romain
                    // Hermann
                    // Federico
                    // VIctor
    
                    foreach ($history['items'] as $item) {
                        if (
                            isset($item['field']) &&
                            $item['field'] === 'indicateur'
                        ) {
                            $changeDate = new DateTime($history['created']);
                            if (
                                $lastIndicateurChangeDate === null ||
                                $changeDate > $lastIndicateurChangeDate
                            ) {
                                $lastIndicateurChangeDate = $changeDate;
                            }
                        }
                    }
                }
            }
    
            // Si aucun changement d'indicateur, ignorer le ticket
            if (!$lastIndicateurChangeDate) {
                continue;
            }
    
            $monthKey = $lastIndicateurChangeDate->format('Y-m');
    
            if (!isset($monthlyData[$monthKey])) {
                continue;
            }
    
            $duration = 0;
            if (isset($blocker['fields']['resolutiondate'])) {
                $resolvedDate = new DateTime($blocker['fields']['resolutiondate']);
                $duration = $resolvedDate->diff($lastIndicateurChangeDate)->days;
            }
    
            $monthlyData[$monthKey]['count']++;
            $monthlyData[$monthKey]['totalDuration'] += $duration;
            $monthlyData[$monthKey]['blockers'][] = [
                'key' => $blocker['key'],
                'summary' => $blocker['fields']['summary'],
                'created' => $lastIndicateurChangeDate->format('Y-m-d H:i'),
                'resolved' => isset($blocker['fields']['resolutiondate']) ? (new DateTime($blocker['fields']['resolutiondate']))->format('Y-m-d H:i') : null,
                'duration' => $duration,
                'link' => sprintf('https://studi-pedago.atlassian.net/browse/%s', $blocker['key'])
            ];
        }
    
        // Statistiques globales
        $totalBlockers = 0;
        $totalDuration = 0;
        foreach ($monthlyData as $data) {
            $totalBlockers += $data['count'];
            $totalDuration += $data['totalDuration'];
        }
    
        // Calcul total des tâches créées dans la période
        $jql = sprintf(
            'project = "MD" AND updated >= "%s" AND updated <= "%s"',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
    
        try {
            // Essayer d'abord avec l'API v3 enhanced search
            $response = $this->jiraClient->request('GET', "/rest/api/3/search/jql", [
                'query' => [
                    'jql' => $jql,
                    'maxResults' => 1, // On récupère juste 1 résultat pour avoir le total
                    'fields' => 'key'
                ]
            ]);
            $data = $response->toArray(false);
            $totalTasks = $data['total'] ?? 0;
        } catch (\Throwable $e) {
            // Fallback vers l'API v3 classique
            try {
                $response = $this->jiraClient->request('POST', "/rest/api/3/search", [
                    'json' => [
                        'jql' => $jql,
                        'maxResults' => 1,
                        'fields' => ['key']
                    ]
                ]);
                $data = $response->toArray(false);
                $totalTasks = $data['total'] ?? 0;
            } catch (\Throwable $e2) {
                // Si tout échoue, utiliser une estimation basée sur les blocages
                $this->logger->warning('Impossible de récupérer le total des tâches: ' . $e2->getMessage());
                $totalTasks = $totalBlockers * 10; // Estimation conservatrice
            }
        }
    
        $blockedPercentage = $totalTasks > 0 ? round(($totalBlockers / $totalTasks) * 100, 1) : 0;
        $averageDuration = $totalBlockers > 0 ? round($totalDuration / $totalBlockers, 1) : 0;
    
        return [
            'monthly' => array_values($monthlyData),
            'total' => $totalBlockers,
            'averageDuration' => $averageDuration,
            'blockedPercentage' => $blockedPercentage,
            'totalTasks' => $totalTasks,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ];
    }
    

    public function getCurrentSprintBlockers(): array
    {
        // Récupérer le sprint actif
        $response = $this->jiraClient->request('GET', "/rest/agile/1.0/board/{$this->jiraBoardId}/sprint", [
            'query' => [
                'state' => 'active'
            ]
        ]);
        
        $data = $response->toArray();
        if (empty($data['values'])) {
            return [
                'blockers' => [],
                'total' => 0,
                'sprint' => null
            ];
        }

        $currentSprint = $data['values'][0];

        // Récupérer les blocages du sprint
        $jql = sprintf(
            'project = MD AND sprint = %d AND "indicateur[checkboxes]" IN (Impediment, Bloquée) ORDER BY created ASC',
            $currentSprint['id']
        );


        $response = $this->jiraClient->request('GET', "/rest/api/3/search", [
            'query' => [
                'jql' => $jql,
                'maxResults' => 100,
                'fields' => 'key,summary,created,resolutiondate,status,assignee,sprint'
            ]
        ]);

        $data = $response->toArray();
        $blockers = [];

        foreach ($data['issues'] as $blocker) {


            $duration = 0;
            if (isset($blocker['fields']['resolutiondate'])) {
                $resolvedDate = new DateTime($blocker['fields']['resolutiondate']);
                $createdDate = new DateTime($blocker['fields']['created']);
                $duration = $resolvedDate->diff($createdDate)->days;
            }

            $blockers[] = [
                'key' => $blocker['key'],
                'summary' => $blocker['fields']['summary'],
                'created' => (new DateTime($blocker['fields']['created']))->format('Y-m-d H:i'),
                'resolved' => isset($blocker['fields']['resolutiondate']) ? (new DateTime($blocker['fields']['resolutiondate']))->format('Y-m-d H:i') : null,
                'duration' => $duration,
                'status' => $blocker['fields']['status']['name'],
                'assignee' => $blocker['fields']['assignee'] ? $blocker['fields']['assignee']['displayName'] : 'Non assigné',
                'link' => sprintf('https://studi-pedago.atlassian.net/browse/%s', $blocker['key'])
            ];
        }

        return [
            'blockers' => $blockers,
            'total' => count($blockers),
            'sprint' => [
                'name' => $currentSprint['name'],
                'startDate' => (new DateTime($currentSprint['startDate']))->format('Y-m-d'),
                'endDate' => (new DateTime($currentSprint['endDate']))->format('Y-m-d'),
                'link' => sprintf(
                    'https://studi-pedago.atlassian.net/issues/?jql=project = MD'
                )
            ]
        ];
    }

    private function fetchBlockers(DateTime $startDate, DateTime $endDate): array
    {
        $allBlockers = [];
        
        $jql = sprintf(
            'project = "MD" AND updated >= "%s" AND updated <= "%s" ORDER BY updated ASC',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
        
        $maxResults = 100; // autorisé par la v3
        $fieldsCsv = 'key,summary,created,resolutiondate,status'; // CSV, pas tableau
        
        $nextPageToken = null;
        $pageSafeguard = 0; 
        $pageLimit = 200; // garde-fou (20k issues max)
        
        try {
            do {
                $query = [
                    'jql'        => $jql,
                    'maxResults' => $maxResults,
                    'fields'     => $fieldsCsv,
                    'expand'     => 'changelog'
                ];
                if ($nextPageToken) {
                    $query['nextPageToken'] = $nextPageToken;
                }
                
                $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', [
                    'query' => $query,
                ]);
                
                $data = $response->toArray(false);
                
                // la réponse enhanced n'a pas "total" ; on lit les issues et le nextPageToken
                $issues = $data['issues'] ?? [];
                foreach ($issues as $issue) {
                    if (!isset($issue['changelog']['histories'])) {
                        continue;
                    }

                    $becameBlocker = false;
                    $blockerDate = null;

                    foreach ($issue['changelog']['histories'] as $history) {
                        $changeDate = new DateTime($history['created']);

                        foreach ($history['items'] as $item) {
                            if ($item['field'] === 'indicateur') {
                                $fromString = $item['fromString'] ?? '';
                                $toString = $item['toString'] ?? '';
                                
                                // Vérifier si le ticket est devenu un blocage
                                if (
                                    !str_contains($fromString, 'Impediment') && 
                                    !str_contains($fromString, 'Bloquée') &&
                                    (str_contains($toString, 'Impediment') || str_contains($toString, 'Bloquée'))
                                ) {
                                    $becameBlocker = true;
                                    $blockerDate = $changeDate;
                                    break 2;
                                }
                            }
                        }
                    }
                    
                    if ($becameBlocker) {
                        $allBlockers[] = $issue;
                    }
                }
                
                $nextPageToken = $data['nextPageToken'] ?? null;
                $pageSafeguard++;
                
            } while ($nextPageToken !== null && $pageSafeguard < $pageLimit);
            
            if ($pageSafeguard >= $pageLimit) {
                error_log('WARN: Pagination interrompue (limite de sécurité atteinte).');
            }
            
            // Succès : on a tout récupéré via /search/jql ; on retourne.
            return $allBlockers;
            
        } catch (\Throwable $t) {
            // Fallback legacy si /search/jql n'est pas dispo sur l'instance (ou feature flag)
            error_log('INFO: fallback /rest/api/3/search (legacy) : ' . $t->getMessage());
        }
        
        // --- Fallback legacy /rest/api/3/search (startAt/total) ---
        $startAt = 0;
        $pageSafeguard = 0;
        do {
            $response = $this->jiraClient->request('POST', '/rest/api/3/search', [
                'json' => [
                    'jql'        => $jql,
                    'startAt'    => $startAt,
                    'maxResults' => $maxResults,
                    'fields'     => ['key', 'summary', 'created', 'resolutiondate', 'status'],
                    'expand'     => 'changelog'
                ],
            ]);
            $data = $response->toArray(false);
            
            $issues = $data['issues'] ?? [];
            foreach ($issues as $issue) {
                if (!isset($issue['changelog']['histories'])) {
                    continue;
                }

                $becameBlocker = false;
                $blockerDate = null;

                foreach ($issue['changelog']['histories'] as $history) {
                    $changeDate = new DateTime($history['created']);

                    foreach ($history['items'] as $item) {
                        if ($item['field'] === 'indicateur') {
                            $fromString = $item['fromString'] ?? '';
                            $toString = $item['toString'] ?? '';
                            
                            // Vérifier si le ticket est devenu un blocage
                            if (
                                !str_contains($fromString, 'Impediment') && 
                                !str_contains($fromString, 'Bloquée') &&
                                (str_contains($toString, 'Impediment') || str_contains($toString, 'Bloquée'))
                            ) {
                                $becameBlocker = true;
                                $blockerDate = $changeDate;
                                break 2;
                            }
                        }
                    }
                }
                
                if ($becameBlocker) {
                    $allBlockers[] = $issue;
                }
            }
            
            $returned = count($issues);
            $total    = isset($data['total']) ? (int)$data['total'] : null;
            
            // stop si plus rien, ou si on a atteint le total
            if ($returned === 0 || ($total !== null && $startAt + $returned >= $total)) {
                break;
            }
            
            $startAt += $returned;
            $pageSafeguard++;
        } while ($pageSafeguard < $pageLimit);
        
        return $allBlockers;
    }
}
