<?php

namespace App\Service;

use DateTime;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class DeploymentService
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getDeploymentData(DateTime $startDate, DateTime $endDate): array
    {
        $jql = sprintf(
            'project = MD AND issuetype = Deployment AND created >= "%s" AND created <= "%s" ORDER BY created ASC',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
            'query' => [
                'jql' => $jql,
                'maxResults' => self::MAX_RESULTS_PER_PAGE,
                'fields' => 'key,summary,created,resolutiondate,status'
            ]
        ]);

        $data = $response->toArray();
        $deployments = [];
        $monthlyDeployments = [];

        foreach ($data['issues'] as $deployment) {
            $createdDate = new DateTime($deployment['fields']['created']);
            $monthKey = $createdDate->format('Y-m');

            if (!isset($monthlyDeployments[$monthKey])) {
                $monthlyDeployments[$monthKey] = [
                    'count' => 0,
                    'deployments' => []
                ];
            }

            $monthlyDeployments[$monthKey]['count']++;
            $monthlyDeployments[$monthKey]['deployments'][] = [
                'key' => $deployment['key'],
                'summary' => $deployment['fields']['summary'],
                'created' => $createdDate->format('Y-m-d H:i'),
                'status' => $deployment['fields']['status']['name'],
                'link' => sprintf('https://studi-pedago.atlassian.net/browse/%s', $deployment['key'])
            ];

            $deployments[] = [
                'key' => $deployment['key'],
                'summary' => $deployment['fields']['summary'],
                'created' => $createdDate->format('Y-m-d H:i'),
                'status' => $deployment['fields']['status']['name'],
                'link' => sprintf('https://studi-pedago.atlassian.net/browse/%s', $deployment['key'])
            ];
        }

        // Calculer la fréquence moyenne de déploiement
        $totalDays = $startDate->diff($endDate)->days;
        $totalDeployments = count($deployments);
        $averageFrequency = $totalDays > 0 ? round($totalDeployments / ($totalDays / 30), 1) : 0;

        return [
            'total_deployments' => $totalDeployments,
            'average_frequency' => $averageFrequency,
            'monthly_data' => array_values($monthlyDeployments),
            'deployments' => $deployments
        ];
    }
} 