<?php

namespace App\Service;

use App\Entity\MonthlyStats;
use App\Repository\MonthlyStatsRepository;
use DateTimeImmutable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BugStatsService
{
    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly MonthlyStatsRepository $monthlyStatsRepository,
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
        
        // Get delivered tickets this month
        $deliveredCount = $this->countDeliveredInPeriod($startOfMonth, $endOfMonth);
        $stats->setDeliveredTicketsCount($deliveredCount);
        
        $stats->setSyncedAt(new DateTimeImmutable());
        
        $this->monthlyStatsRepository->save($stats, true);
        
        return $stats;
    }

    private function countBugsInPeriod(\DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        // D'abord, récupérons le projet associé au board
        $boardInfo = $this->getBoardProject();
        
        $jql = $this->buildBugsJQL($boardInfo['projectKey'], $startDate, $endDate);
        return $this->countIssues($jql);
    }

    public function buildBugsJQL(string $projectKey, \DateTimeInterface $startDate, \DateTimeInterface $endDate): string
    {
        return sprintf(
            'project = "%s" AND issuetype = Bug AND created >= "%s" AND created <= "%s" ORDER BY created DESC',
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

        return $this->countIssues($jql);
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
        try {
            $response = $this->jiraClient->request('GET', '/rest/api/2/search', [
                'query' => [
                    'jql' => $jql,
                    'maxResults' => 0 // On ne veut que le total
                ]
            ]);

            $data = $response->toArray();
            return $data['total'];
        } catch (\Exception $e) {
            throw new \RuntimeException('Error executing JQL query: ' . $jql . ' - ' . $e->getMessage());
        }
    }
} 