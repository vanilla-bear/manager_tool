<?php

namespace App\Service;

use DateTime;
use DateTimeImmutable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ThroughputService
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly string $jiraBoardId,
    ) {
    }

    public function getThroughputData(DateTime $startDate, DateTime $endDate): array
    {
        $issues = $this->fetchCompletedIssues($startDate, $endDate);
        
        $monthlyData = [];
        $currentDate = clone $startDate;
        
        // Initialize monthly data
        while ($currentDate <= $endDate) {
            $monthKey = $currentDate->format('Y-m');
            $monthStart = (clone $currentDate)->modify('first day of this month')->format('Y-m-d');
            $monthEnd = (clone $currentDate)->modify('last day of this month')->format('Y-m-d');
            
            $monthlyData[$monthKey] = [
                'total' => 0,
                'features' => 0,
                'bugs' => 0,
                'improvements' => 0,
                'month' => $currentDate->format('F Y'),
                'links' => [
                    'total' => sprintf(
                        'https://studi-pedago.atlassian.net/issues/?jql=project = MD AND status = Terminé AND resolved >= "%s" AND resolved <= "%s" ORDER BY resolved ASC',
                        $monthStart,
                        $monthEnd
                    ),
                    'features' => sprintf(
                        'https://studi-pedago.atlassian.net/issues/?jql=project = MD AND status = Terminé AND issuetype = Story AND resolved >= "%s" AND resolved <= "%s" ORDER BY resolved ASC',
                        $monthStart,
                        $monthEnd
                    ),
                    'bugs' => sprintf(
                        'https://studi-pedago.atlassian.net/issues/?jql=project = MD AND status = Terminé AND issuetype IN (Bug, "Bug task") AND resolved >= "%s" AND resolved <= "%s" ORDER BY resolved ASC',
                        $monthStart,
                        $monthEnd
                    ),
                    'improvements' => sprintf(
                        'https://studi-pedago.atlassian.net/issues/?jql=project = MD AND status = Terminé AND issuetype NOT IN (Story, Bug, "Bug task") AND resolved >= "%s" AND resolved <= "%s" ORDER BY resolved ASC',
                        $monthStart,
                        $monthEnd
                    ),
                ]
            ];
            $currentDate->modify('+1 month');
        }

        // Analyze issues
        foreach ($issues as $issue) {
            if (!isset($issue['fields']['resolutiondate'])) {
                continue;
            }
            
            $completedDate = new DateTime($issue['fields']['resolutiondate']);
            $monthKey = $completedDate->format('Y-m');
            
            if (!isset($monthlyData[$monthKey])) {
                continue;
            }

            $monthlyData[$monthKey]['total']++;
            
            // Categorize by issue type
            $issueType = strtolower($issue['fields']['issuetype']['name']);
            if (str_contains($issueType, 'bug')) {
                $monthlyData[$monthKey]['bugs']++;
            } elseif (str_contains($issueType, 'story')) {
                $monthlyData[$monthKey]['features']++;
            } else {
                $monthlyData[$monthKey]['improvements']++;
            }
        }

        // Calculate averages
        $totalMonths = count($monthlyData);
        $averages = [
            'total' => 0,
            'features' => 0,
            'bugs' => 0,
            'improvements' => 0,
        ];

        foreach ($monthlyData as $data) {
            $averages['total'] += $data['total'];
            $averages['features'] += $data['features'];
            $averages['bugs'] += $data['bugs'];
            $averages['improvements'] += $data['improvements'];
        }

        if ($totalMonths > 0) {
            $averages['total'] = round($averages['total'] / $totalMonths, 1);
            $averages['features'] = round($averages['features'] / $totalMonths, 1);
            $averages['bugs'] = round($averages['bugs'] / $totalMonths, 1);
            $averages['improvements'] = round($averages['improvements'] / $totalMonths, 1);
        }

        return [
            'monthly' => array_values($monthlyData),
            'averages' => $averages,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ];
    }

    private function fetchCompletedIssues(DateTime $startDate, DateTime $endDate): array
    {
        $allIssues = [];
        
            $jql = sprintf(
                'project = "MD" AND status = "Terminé" AND resolved >= "%s" AND resolved <= "%s" ORDER BY resolved ASC',
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );
        
        $maxResults = 100; // autorisé par la v3
        $fieldsCsv = 'key,summary,issuetype,status,resolutiondate,created,updated'; // CSV, pas tableau
        
        $nextPageToken = null;
        $pageSafeguard = 0; 
        $pageLimit = 200; // garde-fou (20k issues max)
        
        try {
            do {
                $query = [
                    'jql'        => $jql,
                    'maxResults' => $maxResults,
                    'fields'     => $fieldsCsv,
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
                    $allIssues[] = $issue; // on a déjà les champs utiles
                }
                
                $nextPageToken = $data['nextPageToken'] ?? null;
                $pageSafeguard++;
                
            } while ($nextPageToken !== null && $pageSafeguard < $pageLimit);
            
            if ($pageSafeguard >= $pageLimit) {
                error_log('WARN: Pagination interrompue (limite de sécurité atteinte).');
            }
            
            // Succès : on a tout récupéré via /search/jql ; on retourne.
            return $allIssues;
            
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
                    'fields'     => ['key', 'summary', 'issuetype', 'status', 'resolutiondate', 'created', 'updated'],
                ],
            ]);
            $data = $response->toArray(false);
            
            $issues = $data['issues'] ?? [];
            foreach ($issues as $issue) {
                $allIssues[] = $issue;
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
        
        return $allIssues;
    }
} 