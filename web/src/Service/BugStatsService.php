<?php

namespace App\Service;

use App\Entity\MonthlyStats;
use App\Entity\Sprint;
use App\Repository\MonthlyStatsRepository;
use App\Repository\SprintRepository;
use DateTimeImmutable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BugStatsService
{
    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly MonthlyStatsRepository $monthlyStatsRepository,
        private readonly SprintRepository $sprintRepository,
        private readonly string $jiraBoardId,
    ) {
    }

    public function synchronizeMonth(\DateTimeInterface $date): MonthlyStats
    {
        // Get first day of month
        $startOfMonth = new \DateTimeImmutable($date->format('Y-m-01'));
        $endOfMonth = $startOfMonth->modify('last day of this month');
        
        // Find or create stats for this month
        $stats = $this->monthlyStatsRepository->findByMonth($date) ?? new MonthlyStats();
        $stats->setMonth($startOfMonth);
        
        // Get bugs created this month
        $bugsCount = $this->countBugsInPeriod($startOfMonth, $endOfMonth);
        $stats->setBugsCount($bugsCount);
        
        // Get bug tasks created this month
        $bugTasksCount = $this->countBugTasksInPeriod($startOfMonth, $endOfMonth);
        $stats->setBugTasksCount($bugTasksCount);
        
        // Get delivered tickets this month
        $deliveredCount = $this->countDeliveredInPeriod($startOfMonth, $endOfMonth);
        $stats->setDeliveredTicketsCount($deliveredCount);
        
        $stats->setSyncedAt(new DateTimeImmutable());
        
        $this->monthlyStatsRepository->save($stats, true);
        
        return $stats;
    }

    /**
     * Récupère les statistiques de bugs et Bug Tasks par sprint
     */
    public function getSprintBugStats(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        // Récupérer les sprints dans la période
        $sprints = $this->sprintRepository->findByDateRange($startDate, $endDate);
        
        $sprintStats = [];
        $totalBugs = 0;
        $totalBugTasks = 0;
        $totalDelivered = 0;
        
        foreach ($sprints as $sprint) {
            // Compter les bugs créés pendant le sprint
            $bugsCount = $this->countBugsInSprint($sprint);
            
            // Compter les Bug Tasks créés pendant le sprint
            $bugTasksCount = $this->countBugTasksInSprint($sprint);
            
            // Utiliser les points terminés comme proxy pour les features delivered
            $deliveredCount = (int) round($sprint->getCompletedPoints());
            
            $sprintStats[] = [
                'sprint' => $sprint,
                'bugs_count' => $bugsCount,
                'bug_tasks_count' => $bugTasksCount,
                'delivered_count' => $deliveredCount,
                'bug_rate' => $deliveredCount > 0 ? round(($bugsCount / $deliveredCount) * 100, 1) : 0,
                'jql_queries' => [
                    'bugs' => sprintf(
                        'project = "MD" AND issuetype = "Bug" AND created >= "%s" AND created <= "%s"',
                        $sprint->getStartDate()->format('Y-m-d'),
                        $sprint->getEndDate()->format('Y-m-d')
                    ),
                    'bug_tasks' => sprintf(
                        'project = "MD" AND issuetype IN subTaskIssueTypes() AND created >= "%s" AND created <= "%s"',
                        $sprint->getStartDate()->format('Y-m-d'),
                        $sprint->getEndDate()->format('Y-m-d')
                    ),
                ]
            ];
            
            $totalBugs += $bugsCount;
            $totalBugTasks += $bugTasksCount;
            $totalDelivered += $deliveredCount;
        }
        
        return [
            'sprints' => $sprintStats,
            'totals' => [
                'bugs' => $totalBugs,
                'bug_tasks' => $totalBugTasks,
                'delivered' => $totalDelivered,
                'bug_rate' => $totalDelivered > 0 ? round(($totalBugs / $totalDelivered) * 100, 1) : 0,
            ],
            'averages' => [
                'bugs_per_sprint' => count($sprintStats) > 0 ? round($totalBugs / count($sprintStats), 1) : 0,
                'bug_tasks_per_sprint' => count($sprintStats) > 0 ? round($totalBugTasks / count($sprintStats), 1) : 0,
                'delivered_per_sprint' => count($sprintStats) > 0 ? round($totalDelivered / count($sprintStats), 1) : 0,
            ]
        ];
    }

    private function countBugsInPeriod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        // D'abord, récupérons le projet associé au board
        $boardInfo = $this->getBoardProject();
        
        $jql = $this->buildBugsJQL($boardInfo['projectKey'], $startDate, $endDate);
        error_log('BugStatsService: JQL Query for bugs: ' . $jql);
        
        $count = $this->countIssues($jql);
        error_log('BugStatsService: Bugs count result: ' . $count);
        
        return $count;
    }

    public function buildBugsJQL(string $projectKey, \DateTimeInterface $startDate, \DateTimeInterface $endDate): string
    {
        return sprintf(
            'project = "%s" AND issuetype = "Bug" AND created >= "%s" AND created <= "%s" ORDER BY created DESC',
            $projectKey,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
    }

    public function getBugsJQLForMonth(\DateTimeInterface $date): string
    {
        $startOfMonth = new \DateTimeImmutable($date->format('Y-m-01'));
        $endOfMonth = $startOfMonth->modify('last day of this month');
        $boardInfo = $this->getBoardProject();
        
        return $this->buildBugsJQL($boardInfo['projectKey'], $startOfMonth, $endOfMonth);
    }

    private function countDeliveredInPeriod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        // D'abord, récupérons le projet associé au board
        $boardInfo = $this->getBoardProject();
        
        $jql = sprintf(
            'project = "%s" AND status = "Terminé" AND statusCategoryChangedDate >= "%s" AND statusCategoryChangedDate <= "%s"',
            $boardInfo['projectKey'],
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        error_log('BugStatsService: JQL Query for delivered tickets: ' . $jql);
        $count = $this->countIssues($jql);
        error_log('BugStatsService: Delivered tickets count result: ' . $count);
        
        return $count;
    }

    private function getBoardProject(): array
    {
        $response = $this->jiraClient->request('GET', "/rest/agile/1.0/board/{$this->jiraBoardId}");
        $data = $response->toArray();
        
        if (!isset($data['location']['projectKey'])) {
            throw new \RuntimeException('Could not determine project key from board');
        }

        return [
            'projectKey' => $data['location']['projectKey'],
            'projectId' => $data['location']['projectId'],
        ];
    }

    private function countIssues(string $jql): int
    {
        // Utiliser l'API v3 enhanced search avec pagination
        $total = 0;
        $nextPageToken = null;
        $maxResults = 100;
        $pageSafeguard = 0;
        $pageLimit = 200; // garde-fou (20k issues max)

        try {
            do {
                $query = [
                    'jql'        => $jql,
                    'maxResults' => $maxResults,
                    'fields'     => 'key',
                ];
                if ($nextPageToken) {
                    $query['nextPageToken'] = $nextPageToken;
                }

                $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', [
                    'query' => $query
                ]);
                $data = $response->toArray(false);

                $issues = $data['issues'] ?? [];
                $total += count($issues);
                $nextPageToken = $data['nextPageToken'] ?? null;

                $pageSafeguard++;
            } while ($nextPageToken !== null && $pageSafeguard < $pageLimit);

            if ($pageSafeguard >= $pageLimit) {
                error_log('WARN: Pagination interrompue (limite de sécurité atteinte).');
            }

            return $total;
        } catch (\Throwable $e) {
            error_log('Erreur lors du comptage des issues: ' . $e->getMessage());
            return 0;
        }
    }

    private function countBugTasksInPeriod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        $jql = sprintf(
            'project = "MD" AND issuetype IN subTaskIssueTypes() AND created >= "%s" AND created <= "%s"',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        try {
            $total = 0;
            $nextPageToken = null;
            $pageSafeguard = 0;
            $pageLimit = 200;
            $maxResults = 100;

            do {
                $query = [
                    'jql'        => $jql,
                    'maxResults' => $maxResults,
                    'fields'     => 'key',
                ];
                if ($nextPageToken) {
                    $query['nextPageToken'] = $nextPageToken;
                }

                $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', ['query' => $query]);
                $data = $response->toArray(false);

                $issues = $data['issues'] ?? [];
                $total += count($issues);
                $nextPageToken = $data['nextPageToken'] ?? null;

                $pageSafeguard++;
            } while ($nextPageToken !== null && $pageSafeguard < $pageLimit);

            if ($pageSafeguard >= $pageLimit) {
                error_log('WARN: Bug Tasks pagination interrompue (limite de sécurité atteinte).');
            }

            return $total;
        } catch (\Throwable $e) {
            error_log('Erreur lors du comptage des Bug Tasks: ' . $e->getMessage());
            return 0;
        }
    }

    private function countBugsInSprint(Sprint $sprint): int
    {
        $jql = sprintf(
            'project = "MD" AND issuetype = "Bug" AND created >= "%s" AND created <= "%s"',
            $sprint->getStartDate()->format('Y-m-d'),
            $sprint->getEndDate()->format('Y-m-d')
        );

        try {
            $total = 0;
            $nextPageToken = null;
            $pageSafeguard = 0;
            $pageLimit = 200;
            $maxResults = 100;

            do {
                $query = [
                    'jql'        => $jql,
                    'maxResults' => $maxResults,
                    'fields'     => 'key',
                ];
                if ($nextPageToken) {
                    $query['nextPageToken'] = $nextPageToken;
                }

                $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', ['query' => $query]);
                $data = $response->toArray(false);

                $issues = $data['issues'] ?? [];
                $total += count($issues);
                $nextPageToken = $data['nextPageToken'] ?? null;

                $pageSafeguard++;
            } while ($nextPageToken !== null && $pageSafeguard < $pageLimit);

            return $total;
        } catch (\Throwable $e) {
            error_log('Erreur lors du comptage des bugs pour le sprint ' . $sprint->getName() . ': ' . $e->getMessage());
            return 0;
        }
    }

    private function countBugTasksInSprint(Sprint $sprint): int
    {
        $jql = sprintf(
            'project = "MD" AND issuetype IN subTaskIssueTypes() AND created >= "%s" AND created <= "%s"',
            $sprint->getStartDate()->format('Y-m-d'),
            $sprint->getEndDate()->format('Y-m-d')
        );

        try {
            $total = 0;
            $nextPageToken = null;
            $pageSafeguard = 0;
            $pageLimit = 200;
            $maxResults = 100;

            do {
                $query = [
                    'jql'        => $jql,
                    'maxResults' => $maxResults,
                    'fields'     => 'key',
                ];
                if ($nextPageToken) {
                    $query['nextPageToken'] = $nextPageToken;
                }

                $response = $this->jiraClient->request('GET', '/rest/api/3/search/jql', ['query' => $query]);
                $data = $response->toArray(false);

                $issues = $data['issues'] ?? [];
                $total += count($issues);
                $nextPageToken = $data['nextPageToken'] ?? null;

                $pageSafeguard++;
            } while ($nextPageToken !== null && $pageSafeguard < $pageLimit);

            return $total;
        } catch (\Throwable $e) {
            error_log('Erreur lors du comptage des Bug Tasks pour le sprint ' . $sprint->getName() . ': ' . $e->getMessage());
            return 0;
        }
    }
    
} 