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
        $errors = [];

        foreach ($sprints as $sprintData) {
            $current++;
            if ($progressCallback) {
                $progressCallback($current, $total, $sprintData['name']);
            }

            try {
                $sprint = $this->sprintRepository->findByJiraId($sprintData['id']) ?? new Sprint();
                
                $this->updateSprintFromJiraData($sprint, $sprintData);
                $this->sprintRepository->save($sprint, true);
                
                $syncedSprints[] = $sprint;
            } catch (\Exception $e) {
                $errorMessage = sprintf(
                    'Erreur lors de la synchronisation du sprint "%s" (ID: %d): %s',
                    $sprintData['name'] ?? 'Inconnu',
                    $sprintData['id'] ?? 'N/A',
                    $e->getMessage()
                );
                $errors[] = $errorMessage;
                
                // Log l'erreur mais continue avec le sprint suivant
                error_log($errorMessage);
                
                // Appeler le callback avec l'erreur pour l'affichage
                if ($progressCallback) {
                    $progressCallback($current, $total, $sprintData['name'] . ' (ERREUR)');
                }
            }
        }

        // Si il y a des erreurs, les logger
        if (!empty($errors)) {
            error_log(sprintf('Synchronisation terminée avec %d erreurs sur %d sprints', count($errors), $total));
        }

        return $syncedSprints;
    }

    private function fetchSprintsFromJira(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $allSprints = [];
        $startAt = 0;
    
        do {
            $response = $this->jiraClient->request('GET', "/rest/agile/1.0/board/{$this->jiraBoardId}/sprint", [
                'query' => [
                    'state'      => 'active,closed',
                    'startAt'    => $startAt,
                    'maxResults' => self::MAX_RESULTS_PER_PAGE,
                ],
            ]);
    
            $data = $response->toArray(false);
            $allSprints = array_merge($allSprints, $data['values'] ?? []);
    
            $isLast = $data['isLast'] ?? true; // Agile renvoie isLast
            $startAt += self::MAX_RESULTS_PER_PAGE;
        } while (!$isLast); // boucle tant que pas la dernière page
        // (Certaines instances n’ont pas de total fiable.) :contentReference[oaicite:6]{index=6}
    
        $filtered = array_filter($allSprints, function ($sprint) use ($startDate, $endDate) {
            if (empty($sprint['startDate'])) return false;
    
            try {
                $sprintStart = new \DateTime($sprint['startDate']);
    
                if (($sprint['state'] ?? null) === 'active') {
                    return $sprintStart >= $startDate;
                }
    
                if (empty($sprint['endDate'])) return false;
    
                $sprintEnd = new \DateTime($sprint['endDate']);
                return $sprintStart >= $startDate && $sprintEnd <= $endDate;
            } catch (\Throwable) {
                return false;
            }
        });
    
        // Réindexer proprement
        return array_values($filtered);
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
        $allIssues = [];
    
        // ⚠️ Jira Cloud "enhanced search" (GET /rest/api/3/search/jql)
        // utilise nextPageToken. On tente d'abord cette route.
        $jql = sprintf(
            'project = MD AND sprint = %d AND issuetype in (Bug, Epic, Story, Task, Technique) ORDER BY created ASC',
            $sprintId
        );
    
        $maxResults = 100; // autorisé par la v3
        $fieldsCsv = 'key,status,customfield_10200,created,updated'; // CSV, pas tableau
    
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
    
                // la réponse enhanced n’a pas “total” ; on lit les issues et le nextPageToken
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
            // Fallback legacy si /search/jql n’est pas dispo sur l’instance (ou feature flag)
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
                    'fields'     => ['key', 'status', 'customfield_10200', 'created', 'updated'],
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
    


    private function fetchSprintVelocityData(int $sprintId): array
    {
        $completedPoints = 0.0;
        $committedPoints = 0.0;
        $devsTerminesPoints = 0.0;
        $devsTerminesCount = 0;
        $addedPointsDuringSprint = 0.0;
        $capacityDays = 10.0;
    
        try {
            $sprintInfo = $this->fetchSprintInfo($sprintId);
            $sprintStartDate = new \DateTime($sprintInfo['startDate']);
    
            $issues = $this->fetchSprintIssues($sprintId, false);
            dump(count($issues));
            foreach ($issues as $issue) {
                $fields = $issue['fields'] ?? [];
                $storyPoints = $this->getStoryPoints($issue);
    
                $created = isset($fields['created']) ? new \DateTime($fields['created']) : null;
                if ($created && $created > $sprintStartDate) {
                    $addedPointsDuringSprint += $storyPoints;
                } else {
                    $committedPoints += $storyPoints;
                }
    
                $status = $fields['status']['statusCategory']['key'] ?? null;
                if ($status === 'done') {
                    $completedPoints += $storyPoints;
                }
            }
    
            $capacityDays = $this->calculateCapacityDays($sprintId);
    
        } catch (\Throwable $e) {
            error_log(sprintf('Erreur vélocité sprint %d: %s', $sprintId, $e->getMessage()));
        }
    
        return [
            'completedPoints'         => $completedPoints,
            'committedPoints'         => $committedPoints,
            'devsTerminesPoints'      => $devsTerminesPoints,
            'capacityDays'            => $capacityDays,
            'devsTerminesCount'       => $devsTerminesCount,
            'addedPointsDuringSprint' => $addedPointsDuringSprint,
        ];
    }
    

    private function fetchSprintInfo(int $sprintId): array
    {
        try {
            // Utiliser l'API v1 pour récupérer les informations du sprint (l'API v3 n'existe pas pour les sprints)
            $response = $this->jiraClient->request('GET', "/rest/agile/1.0/sprint/{$sprintId}");
            return $response->toArray();
        } catch (\Exception $e) {
            // Si le sprint n'existe plus, on retourne des données par défaut
            error_log(sprintf('Impossible de récupérer les infos du sprint %d: %s', $sprintId, $e->getMessage()));
            return [
                'startDate' => (new \DateTime())->format('Y-m-d'),
                'endDate' => (new \DateTime('+2 weeks'))->format('Y-m-d'),
            ];
        }
    }

    private function getStoryPoints(array $issue): float
    {
        $fields = $issue['fields'];
        
        $storyPointFields = [
            'customfield_10200',  // Story points (champ correct)
            'customfield_10116',  // Story points (alternative)
            'customfield_10016',  // Story points (ancien champ)
            'customfield_10026',  // Story points (alternative)
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