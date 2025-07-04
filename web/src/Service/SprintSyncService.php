<?php

namespace App\Service;

use App\Entity\Sprint;
use App\Repository\SprintRepository;
use DateTimeImmutable;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SprintSyncService
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly SprintRepository $sprintRepository,
        private readonly string $jiraBoardId,
    ) {
    }

    public function synchronizeSprints(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        callable $progressCallback = null
    ): array {
        $sprints = $this->fetchSprintsFromJira($startDate, $endDate);
        $syncedSprints = [];
        $total = count($sprints);
        $current = 0;

        foreach ($sprints as $sprintData) {
            $current++;
            if ($progressCallback) {
                $progressCallback($current, $total, $sprintData['name']);
            }

            $sprint = $this->sprintRepository->findByJiraId($sprintData['id']) ?? new Sprint();
            
            $this->updateSprintFromJiraData($sprint, $sprintData);
            $this->sprintRepository->save($sprint, true);
            
            $syncedSprints[] = $sprint;
        }

        return $syncedSprints;
    }

    private function fetchSprintsFromJira(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $allSprints = [];
        $startAt = 0;
        $isLastPage = false;

        while (!$isLastPage) {
            $response = $this->jiraClient->request('GET', "/rest/agile/1.0/board/{$this->jiraBoardId}/sprint", [
                'query' => [
                    'state' => 'active,closed', // Include active sprints
                    'startAt' => $startAt,
                    'maxResults' => self::MAX_RESULTS_PER_PAGE
                ]
            ]);

            $data = $response->toArray();
            
            // Add sprints from current page
            $allSprints = array_merge($allSprints, $data['values']);

            // Check if we've reached the last page
            $total = $data['total'];
            $startAt += self::MAX_RESULTS_PER_PAGE;
            $isLastPage = $startAt >= $total;
        }

        // Filter sprints by date range
        return array_filter($allSprints, function ($sprint) use ($startDate, $endDate) {
            // Skip sprints without start date
            if (!isset($sprint['startDate'])) {
                return false;
            }

            try {
                $sprintStart = new \DateTime($sprint['startDate']);
                
                // For active sprints, we don't check the end date
                if ($sprint['state'] === 'active') {
                    return $sprintStart >= $startDate;
                }

                // For closed sprints, we check both dates
                if (!isset($sprint['endDate'])) {
                    return false;
                }
                
                $sprintEnd = new \DateTime($sprint['endDate']);
                return $sprintStart >= $startDate && $sprintEnd <= $endDate;
            } catch (\Exception $e) {
                // Skip sprints with invalid dates
                return false;
            }
        });
    }

    private function updateSprintFromJiraData(Sprint $sprint, array $data): void
    {
        $sprint->setJiraId($data['id']);
        $sprint->setName($data['name']);
        $sprint->setStartDate(new DateTimeImmutable($data['startDate']));
        
        // Set end date only if available (might not be for active sprints)
        if (isset($data['endDate'])) {
            $sprint->setEndDate(new DateTimeImmutable($data['endDate']));
        } elseif (!$sprint->getEndDate()) {
            // If no end date and not previously set, use start date + 2 weeks
            $endDate = new \DateTime($data['startDate']);
            $endDate->modify('+2 weeks');
            $sprint->setEndDate(DateTimeImmutable::createFromMutable($endDate));
        }
        
        // Fetch and set velocity data
        $velocityData = $this->fetchSprintVelocityData($data['id']);
        $sprint->setCompletedPoints($velocityData['completedPoints']);
        $sprint->setCommittedPoints($velocityData['committedPoints']);
        $sprint->setDevsTerminesPoints($velocityData['devsTerminesPoints']);
        $sprint->setDevsTerminesCount($velocityData['devsTerminesCount']);
        $sprint->setAddedPointsDuringSprint($velocityData['addedPointsDuringSprint']);
        
        // If planned capacity is not set, initialize it with actual capacity
        if ($sprint->getPlannedCapacityDays() === null) {
            $sprint->setPlannedCapacityDays($velocityData['capacityDays']);
        }
        
        $sprint->setCapacityDays($velocityData['capacityDays']);
        $sprint->setSyncedAt(new DateTimeImmutable());
    }

    private function fetchSprintIssues(int $sprintId, bool $initialState = false): array
    {
        $startAt = 0;
        $isLastPage = false;
        $allIssues = [];
        
        while (!$isLastPage) {
            $jql = sprintf('project = MD AND sprint = %d AND issuetype in (Bug, Epic, Story, Task, Technique) ORDER BY created ASC', $sprintId);

            $response = $this->jiraClient->request('GET', '/rest/api/2/search', [
                'query' => [
                    'jql' => $jql,
                    'startAt' => $startAt,
                    'maxResults' => self::MAX_RESULTS_PER_PAGE,
                    'fields' => [
                        'key',
                        'status',
                        'customfield_10016',  // Story points
                        'customfield_10026',  // Story points (alternative)
                        'customfield_10200',  // Story points (alternative)
                        'created',            // Date de création
                        'updated',            // Date de mise à jour
                        'sprint'              // Champ sprint pour voir l'historique
                    ],
                    'expand' => 'changelog'   // Récupérer l'historique des changements
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

    private function fetchSprintVelocityData(int $sprintId): array
    {
        $completedPoints = 0;
        $committedPoints = 0;
        $devsTerminesPoints = 0;
        $devsTerminesCount = 0;
        $addedPointsDuringSprint = 0;
        
        // Récupérer tous les tickets du sprint
        $allIssues = $this->fetchSprintIssues($sprintId, false);
        
        // Récupérer les dates du sprint
        $sprintInfo = $this->fetchSprintInfo($sprintId);
        $sprintStartDate = new \DateTime($sprintInfo['startDate']);
        
        foreach ($allIssues as $issue) {
            $storyPoints = $this->getStoryPoints($issue);
            $issueCreated = new \DateTime($issue['fields']['created']);
            
            // Si le ticket a été créé après le début du sprint, c'est un ajout
            if ($issueCreated > $sprintStartDate) {
                $addedPointsDuringSprint += $storyPoints;
            } else {
                $committedPoints += $storyPoints;
            }
            
            // Si le ticket est terminé (Done), on le compte dans completedPoints
            if ($issue['fields']['status']['statusCategory']['key'] === 'done') {
                $completedPoints += $storyPoints;
            }
            
            // Si le ticket est passé par le statut "Dev Terminé", on le compte dans devsTerminesPoints
            if ($this->hasPassedThroughDevTermineStatus($issue)) {
                $devsTerminesPoints += $storyPoints;
                $devsTerminesCount++;
            }
        }

        // Calculer la capacité en jours
        $capacityDays = $this->calculateCapacityDays($sprintId);

        return [
            'completedPoints' => $completedPoints,
            'committedPoints' => $committedPoints,
            'devsTerminesPoints' => $devsTerminesPoints,
            'capacityDays' => $capacityDays,
            'devsTerminesCount' => $devsTerminesCount,
            'addedPointsDuringSprint' => $addedPointsDuringSprint
        ];
    }

    /**
     * Vérifie si un ticket est passé par le statut "Dev Terminé" en analysant son changelog
     */
    private function hasPassedThroughDevTermineStatus(array $issue): bool
    {
        // Vérifier d'abord le statut actuel
        $currentStatus = $issue['fields']['status']['name'];
        if ($currentStatus === 'Dev Terminé') {
            return true;
        }
        
        // Vérifier l'historique des changements
        if (!isset($issue['changelog']) || !isset($issue['changelog']['histories'])) {
            return false;
        }
        
        foreach ($issue['changelog']['histories'] as $history) {
            if (!isset($history['items'])) {
                continue;
            }
            
            foreach ($history['items'] as $item) {
                // Vérifier si c'est un changement de statut
                if ($item['field'] === 'status') {
                    $fromStatus = $item['fromString'] ?? '';
                    $toStatus = $item['toString'] ?? '';
                    
                    // Si le ticket est passé par "Dev Terminé" (dans n'importe quel sens)
                    if ($fromStatus === 'Dev Terminé' || $toStatus === 'Dev Terminé') {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    private function fetchSprintInfo(int $sprintId): array
    {
        $response = $this->jiraClient->request('GET', "/rest/agile/1.0/sprint/{$sprintId}");
        return $response->toArray();
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

    private function calculateCapacityDays(int $sprintId): float
    {
        // This is a simplified calculation. In a real implementation,
        // you would need to consider:
        // - Team members assigned to the sprint
        // - Working days in the sprint (excluding holidays)
        // - Individual capacity/availability
        // For now, we'll return a default value
        return 10.0; // Assuming 2 weeks sprint with 5 team members at 100% capacity
    }
} 