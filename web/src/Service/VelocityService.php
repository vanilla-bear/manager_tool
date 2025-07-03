<?php

namespace App\Service;

use DateTime;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class VelocityService
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly string $jiraBoardId,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getVelocityData(DateTime $startDate, DateTime $endDate): array
    {
        $response = $this->jiraClient->request('GET', "/rest/agile/1.0/board/{$this->jiraBoardId}/sprint", [
            'query' => [
                'state' => 'closed',
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d')
            ]
        ]);

        $data = $response->toArray();
        $sprints = [];

        foreach ($data['values'] as $sprint) {
            $sprintData = $this->getSprintData($sprint['id']);
            if ($sprintData) {
                $sprints[] = $sprintData;
            }
        }

        return [
            'sprints' => $sprints,
            'average_velocity' => $this->calculateAverageVelocity($sprints),
            'velocity_trend' => $this->calculateVelocityTrend($sprints)
        ];
    }

    private function getSprintData(int $sprintId): ?array
    {
        $response = $this->jiraClient->request('GET', "/rest/agile/1.0/sprint/{$sprintId}");
        $sprint = $response->toArray();

        $jql = sprintf(
            'project = MD AND sprint = %d AND status = Done ORDER BY created ASC',
            $sprintId
        );

        $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
            'query' => [
                'jql' => $jql,
                'maxResults' => self::MAX_RESULTS_PER_PAGE,
                'fields' => 'key,summary,storypoints'
            ]
        ]);

        $data = $response->toArray();
        $completedPoints = 0;
        $completedIssues = [];

        foreach ($data['issues'] as $issue) {
            if (isset($issue['fields']['storypoints'])) {
                $completedPoints += (int)$issue['fields']['storypoints'];
            }
            $completedIssues[] = [
                'key' => $issue['key'],
                'summary' => $issue['fields']['summary'],
                'points' => $issue['fields']['storypoints'] ?? 0
            ];
        }

        return [
            'id' => $sprint['id'],
            'name' => $sprint['name'],
            'start_date' => $sprint['startDate'],
            'end_date' => $sprint['endDate'],
            'completed_points' => $completedPoints,
            'completed_issues' => $completedIssues
        ];
    }

    private function calculateAverageVelocity(array $sprints): float
    {
        if (empty($sprints)) {
            return 0;
        }

        $totalPoints = array_sum(array_column($sprints, 'completed_points'));
        return round($totalPoints / count($sprints), 1);
    }

    private function calculateVelocityTrend(array $sprints): array
    {
        $trend = [];
        foreach ($sprints as $sprint) {
            $trend[] = [
                'sprint' => $sprint['name'],
                'velocity' => $sprint['completed_points']
            ];
        }
        return $trend;
    }
} 