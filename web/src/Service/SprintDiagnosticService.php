<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SprintDiagnosticService
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly string $jiraBoardId,
    ) {
    }

    public function analyzeSprint(string $sprintName): array
    {
        // 1. Find sprint by name
        $sprint = $this->findSprintByName($sprintName);
        if (!$sprint) {
            throw new \RuntimeException("Sprint not found: {$sprintName}");
        }

        // 2. Get all issues and analyze them
        $issues = $this->fetchSprintIssues($sprint['id']);
        
        $analysis = [
            'sprint' => $sprint,
            'issues' => [],
            'totals' => [
                'completed' => 0.0,
                'committed' => 0.0,
                'added' => 0.0,
            ],
            'details' => []
        ];

        $sprintStartDate = new \DateTime($sprint['startDate']);
        
        foreach ($issues as $issue) {
            $storyPoints = $this->getStoryPoints($issue);
            $issueCreated = new \DateTime($issue['fields']['created']);
            $isDone = $issue['fields']['status']['statusCategory']['key'] === 'done';
            
            $issueAnalysis = [
                'key' => $issue['key'],
                'points' => $storyPoints,
                'status' => $issue['fields']['status']['name'],
                'isDone' => $isDone,
                'created' => $issueCreated->format('Y-m-d H:i:s'),
                'isAdded' => $issueCreated > $sprintStartDate,
            ];

            if ($issueAnalysis['isAdded']) {
                $analysis['totals']['added'] += $storyPoints;
            } else {
                $analysis['totals']['committed'] += $storyPoints;
            }

            if ($isDone) {
                $analysis['totals']['completed'] += $storyPoints;
            }

            $analysis['issues'][] = $issueAnalysis;
        }

        return $analysis;
    }

    private function findSprintByName(string $sprintName): ?array
    {
        $startAt = 0;
        $isLastPage = false;

        while (!$isLastPage) {
            $response = $this->jiraClient->request('GET', "/rest/agile/1.0/board/{$this->jiraBoardId}/sprint", [
                'query' => [
                    'state' => 'active,closed',
                    'startAt' => $startAt,
                    'maxResults' => self::MAX_RESULTS_PER_PAGE
                ]
            ]);

            $data = $response->toArray();
            
            foreach ($data['values'] as $sprint) {
                if ($sprint['name'] === $sprintName) {
                    return $sprint;
                }
            }

            $total = $data['total'];
            $startAt += self::MAX_RESULTS_PER_PAGE;
            $isLastPage = $startAt >= $total;
        }

        return null;
    }

    private function fetchSprintIssues(int $sprintId): array
    {
        $startAt = 0;
        $isLastPage = false;
        $allIssues = [];
        
        while (!$isLastPage) {
            $jql = sprintf('project = MD AND sprint = %d AND issuetype in (Bug, Epic, Story, Task, Technique) ORDER BY created ASC', $sprintId);

            $response = $this->jiraClient->request('GET', "/rest/api/2/search", [
                'query' => [
                    'jql' => $jql,
                    'startAt' => $startAt,
                    'maxResults' => self::MAX_RESULTS_PER_PAGE,
                    'fields' => [
                        'status',
                        'customfield_10016',  // Story points
                        'customfield_10026',  // Story points (alternative)
                        'customfield_10200',  // Story points (alternative)
                        'created',
                        'updated',
                        'summary'
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

    private function getStoryPoints(array $issue): float
    {
        $fields = $issue['fields'];
        
        $storyPointFields = [
            'customfield_10016',
            'customfield_10026',
            'customfield_10200',
        ];
        
        foreach ($storyPointFields as $field) {
            if (isset($fields[$field]) && $fields[$field] !== null) {
                return (float) $fields[$field];
            }
        }
        
        return 0.0;
    }
} 