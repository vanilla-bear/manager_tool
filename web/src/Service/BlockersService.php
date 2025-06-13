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
            'project = MD AND updated >= "%s" AND updated <= "%s"',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
    
        $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
            'query' => [
                'jql' => $jql,
                'maxResults' => 0
            ]
        ]);
        $data = $response->toArray();
        $totalTasks = $data['total'];
    
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


        $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
            'query' => [
                'jql' => $jql,
                'maxResults' => self::MAX_RESULTS_PER_PAGE,
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
        $startAt = 0;
        $allBlockers = [];
        $isLastPage = false;

        while (!$isLastPage) {
            //$jql = sprintf(
            //    'project = MD AND updated >= "%s" AND updated <= "%s" AND "indicateur[checkboxes]" IN (Impediment, Bloquée) ORDER BY updated ASC',
            //    $startDate->format('Y-m-d'),
            //    $endDate->format('Y-m-d')
            //);
            $jql = sprintf(
                'project = MD AND updated >= "%s" AND updated <= "%s" ORDER BY updated ASC',
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );
            //dump($jql);


            $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
                'query' => [
                    'jql' => $jql,
                    'startAt' => $startAt,
                    'maxResults' => self::MAX_RESULTS_PER_PAGE,
                    'fields' => 'key,summary,created,resolutiondate,status',
                    'expand' => 'changelog'
                ]
            ]);

            $data = $response->toArray();
            
            //dump($data);
            foreach ($data['issues'] as $issue) {
                //dump($issue['key']);
                if (!isset($issue['changelog']['histories'])) {
                    continue;
                }

                $becameBlocker = false;
                $blockerDate = null;

                foreach ($issue['changelog']['histories'] as $history) {
                    $changeDate = new DateTime($history['created']);
                    //if ($changeDate < $startDate || $changeDate > $endDate) {
                    //    continue;
                    //}

                    foreach ($history['items'] as $item) {
                        if ($item['field'] === 'indicateur') {
                            //dump($item);

                            $fromString = $item['fromString'] ?? '';
                            $toString = $item['toString'] ?? '';
                            //dump($fromString);
                            //dump("--------------------------------");
                            //dump($toString);
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
                //dump($becameBlocker);
                if ($becameBlocker) {
                    $allBlockers[] = $issue;
                }
                //dump("--------------------------------");
            }

            $total = $data['total'];
            $startAt += self::MAX_RESULTS_PER_PAGE;
            $isLastPage = $startAt >= $total;
        }
        //dd($allBlockers);
        return $allBlockers;
    }
}
