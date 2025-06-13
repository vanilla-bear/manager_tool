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
        $startAt = 0;
        $isLastPage = false;
        $allIssues = [];
        
        while (!$isLastPage) {
            $jql = sprintf(
                'project = MD AND status = Terminé AND resolved >= "%s" AND resolved <= "%s" ORDER BY resolved ASC',
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
                'query' => [
                    'jql' => $jql,
                    'startAt' => $startAt,
                    'maxResults' => self::MAX_RESULTS_PER_PAGE,
                    'fields' => [
                        'key',
                        'summary',
                        'issuetype',
                        'status',
                        'resolved',
                        'created',
                        'updated'
                    ]
                ]
            ]);

            $data = $response->toArray();
            $allIssues = array_merge($allIssues, $data['issues']);

            $total = $data['total'];
            $startAt += self::MAX_RESULTS_PER_PAGE;
            $isLastPage = $startAt >= $total;
        }
        return $allIssues;
    }
} 