<?php

namespace App\Service;

use DateTime;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class BugsService
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getBugsData(DateTime $startDate, DateTime $endDate): array
    {
        $jql = sprintf(
            'project = MD AND issuetype = Bug AND created >= "%s" AND created <= "%s" ORDER BY created ASC',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
            'query' => [
                'jql' => $jql,
                'maxResults' => self::MAX_RESULTS_PER_PAGE,
                'fields' => 'key,summary,created,resolutiondate,status,priority'
            ]
        ]);

        $data = $response->toArray();
        $bugs = [];
        $monthlyBugs = [];
        $priorityStats = [
            'highest' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'lowest' => 0
        ];

        foreach ($data['issues'] as $bug) {
            $createdDate = new DateTime($bug['fields']['created']);
            $monthKey = $createdDate->format('Y-m');

            if (!isset($monthlyBugs[$monthKey])) {
                $monthlyBugs[$monthKey] = [
                    'count' => 0,
                    'bugs' => []
                ];
            }

            $monthlyBugs[$monthKey]['count']++;
            $monthlyBugs[$monthKey]['bugs'][] = [
                'key' => $bug['key'],
                'summary' => $bug['fields']['summary'],
                'created' => $createdDate->format('Y-m-d H:i'),
                'status' => $bug['fields']['status']['name'],
                'priority' => $bug['fields']['priority']['name'],
                'link' => sprintf('https://studi-pedago.atlassian.net/browse/%s', $bug['key'])
            ];

            // Compter les bugs par priorité
            $priority = strtolower($bug['fields']['priority']['name']);
            if (isset($priorityStats[$priority])) {
                $priorityStats[$priority]++;
            }

            $bugs[] = [
                'key' => $bug['key'],
                'summary' => $bug['fields']['summary'],
                'created' => $createdDate->format('Y-m-d H:i'),
                'status' => $bug['fields']['status']['name'],
                'priority' => $bug['fields']['priority']['name'],
                'link' => sprintf('https://studi-pedago.atlassian.net/browse/%s', $bug['key'])
            ];
        }

        // Calculer le taux de bugs par rapport au total des tickets
        $totalTicketsJql = sprintf(
            'project = MD AND created >= "%s" AND created <= "%s"',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
            'query' => [
                'jql' => $totalTicketsJql,
                'maxResults' => 0
            ]
        ]);

        $totalTickets = $response->toArray()['total'];
        $bugRate = $totalTickets > 0 ? round((count($bugs) / $totalTickets) * 100, 1) : 0;

        return [
            'total_bugs' => count($bugs),
            'bug_rate' => $bugRate,
            'priority_distribution' => $priorityStats,
            'monthly_data' => array_values($monthlyBugs),
            'bugs' => $bugs
        ];
    }
} 