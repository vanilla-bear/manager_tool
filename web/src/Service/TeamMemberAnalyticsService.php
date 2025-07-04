<?php

namespace App\Service;

use App\Entity\TeamMember;
use App\Entity\TeamMemberProfile;
use App\Repository\TeamMemberProfileRepository;
use App\Repository\TeamMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class TeamMemberAnalyticsService
{
    private const MAX_RESULTS_PER_PAGE = 100;
    private const ANALYSIS_PERIOD_MONTHS = 12;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamMemberProfileRepository $profileRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $jiraBoardId,
    ) {
    }

    /**
     * Génère les statistiques pour tous les membres de l'équipe
     */
    public function generateAllProfiles(): array
    {
        $teamMembers = $this->teamMemberRepository->findAll();
        $results = [];

        foreach ($teamMembers as $teamMember) {
            if (!$teamMember->getJiraId()) {
                $this->logger->warning("Team member {$teamMember->getName()} has no Jira ID, skipping analysis");
                continue;
            }

            try {
                $profile = $this->generateProfile($teamMember);
                $results[] = $profile;
            } catch (\Exception $e) {
                $this->logger->error("Error generating profile for {$teamMember->getName()}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Génère les statistiques pour un membre spécifique
     */
    public function generateProfile(TeamMember $teamMember): TeamMemberProfile
    {
        $this->logger->info("Starting profile generation for {$teamMember->getName()} ({$teamMember->getJiraId()})");
        
        $periodStart = new \DateTimeImmutable('-' . self::ANALYSIS_PERIOD_MONTHS . ' months');
        $periodEnd = new \DateTimeImmutable();

        // Récupérer ou créer le profil
        $profile = $this->profileRepository->findByTeamMember($teamMember->getId()) ?? new TeamMemberProfile();
        $profile->setTeamMember($teamMember);
        $profile->setPeriodStart($periodStart);
        $profile->setPeriodEnd($periodEnd);
        $profile->setAnalysisDate(new \DateTimeImmutable());

        try {
            // Analyser les données Jira
            $this->logger->info("Fetching Jira data for {$teamMember->getName()}");
            $jiraData = $this->fetchJiraData($teamMember->getJiraId(), $periodStart, $periodEnd);
            
            if (empty($jiraData)) {
                $this->logger->warning("No Jira data found for {$teamMember->getName()}");
                // Créer un profil vide avec des statistiques par défaut
                $profile->setProductivityStats($this->getEmptyProductivityStats());
                $profile->setQualityStats($this->getEmptyQualityStats());
                $profile->setImpactStats($this->getEmptyImpactStats());
                $profile->setCollaborationStats($this->getEmptyCollaborationStats());
                $profile->setEvolutionStats($this->getEmptyEvolutionStats());
                $profile->setQualitativeFeedback([]);
            } else {
                $this->logger->info("Processing " . count($jiraData) . " issues for {$teamMember->getName()}");
                // Calculer la productivité par sprint avec la nouvelle méthode fiable
                $sprintStats = $this->getSprintsProductivityStats($teamMember->getJiraId(), $periodStart, $periodEnd);
                $profile->setProductivityStats($sprintStats);
                $profile->setQualityStats($this->calculateQualityStats($jiraData));
                $profile->setImpactStats($this->calculateImpactStats($jiraData));
                $profile->setCollaborationStats($this->calculateCollaborationStats($jiraData));
                $profile->setEvolutionStats($this->calculateEvolutionStats($jiraData));
                $profile->setQualitativeFeedback($this->extractQualitativeFeedback($jiraData));
            }

            $profile->setLastSyncAt(new \DateTimeImmutable());
            $this->profileRepository->save($profile, true);
            
            $this->logger->info("Profile generation completed successfully for {$teamMember->getName()}");
            
        } catch (\Exception $e) {
            $this->logger->error("Error generating profile for {$teamMember->getName()}: " . $e->getMessage());
            throw $e;
        }

        return $profile;
    }

    /**
     * Récupère toutes les données Jira pour un utilisateur sur une période donnée
     */
    public function fetchJiraData(string $jiraId, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $allIssues = [];
        $startAt = 0;
        $isLastPage = false;
        $maxResults = 50; // Réduire de 100 à 50 pour moins de mémoire
        $maxTotalIssues = 1000; // Limite de sécurité

        while (!$isLastPage) {
            $jql = sprintf(
                'assignee = "%s" AND updated >= "%s" AND updated <= "%s" ORDER BY updated DESC',
                $jiraId,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            $this->logger->info("Fetching Jira data for {$jiraId}, page starting at {$startAt}");

            $response = $this->jiraClient->request('GET', '/rest/api/2/search', [
                'query' => [
                    'jql' => $jql,
                    'startAt' => $startAt,
                    'maxResults' => $maxResults,
                    'fields' => [
                        'key',
                        'summary',
                        'issuetype',
                        'status',
                        'priority',
                        'customfield_10016',  // Story points
                        'customfield_10026',  // Story points (alternative)
                        'customfield_10200',  // Story points (alternative)
                        'created',
                        'updated',
                        'resolutiondate',
                        'assignee',
                        'reporter',
                        'epic',
                        'sprint',
                        'labels',
                        'components',
                        'comment',
                        'changelog'
                    ],
                    'expand' => 'changelog,comments'
                ]
            ]);

            $data = $response->toArray();
            $allIssues = array_merge($allIssues, $data['issues']);

            $total = $data['total'];
            $startAt += $maxResults;
            $isLastPage = $startAt >= $total;

            $this->logger->info("Fetched " . count($data['issues']) . " issues, total: {$total}, progress: " . min(100, round(($startAt / $total) * 100, 1)) . "%");

            // Vérifier la limite de sécurité
            if (count($allIssues) >= $maxTotalIssues) {
                $this->logger->warning("Reached maximum limit of {$maxTotalIssues} issues for {$jiraId}. Stopping fetch.");
                break;
            }

            // Libérer la mémoire
            unset($data);
            gc_collect_cycles();

            // Pause courte pour éviter de surcharger l'API
            if (!$isLastPage) {
                usleep(100000); // 0.1 seconde
            }
        }

        $this->logger->info("Total issues fetched for {$jiraId}: " . count($allIssues));
        return $allIssues;
    }

    /**
     * Calcule les statistiques de productivité
     */
    public function calculateProductivityStats(array $issues): array
    {
        $totalTickets = count($issues);
        $totalPoints = 0;
        $bugTasks = 0;
        $typeDistribution = [];
        $sprintDistribution = [];

        foreach ($issues as $issue) {
            $storyPoints = $this->getStoryPoints($issue);
            $totalPoints += $storyPoints;
            
            $issueType = $issue['fields']['issuetype']['name'];
            $typeDistribution[$issueType] = ($typeDistribution[$issueType] ?? 0) + 1;
            
            if ($issueType === 'Bug') {
                $bugTasks++;
            }

            // Analyser la distribution par sprint
            if (isset($issue['fields']['sprint']) && $issue['fields']['sprint']) {
                $sprintField = $issue['fields']['sprint'];
                // Si c'est un tableau
                if (is_array($sprintField)) {
                    foreach ($sprintField as $sprint) {
                        $sprintName = is_array($sprint) && isset($sprint['name']) ? $sprint['name'] : (string)$sprint;
                        if (!isset($sprintDistribution[$sprintName])) {
                            $sprintDistribution[$sprintName] = [
                                'tickets' => 0,
                                'points' => 0
                            ];
                        }
                        $sprintDistribution[$sprintName]['tickets']++;
                        $sprintDistribution[$sprintName]['points'] += $storyPoints;
                    }
                } else {
                    // Si c'est un objet ou une string
                    $sprintName = is_array($sprintField) && isset($sprintField['name']) ? $sprintField['name'] : (string)$sprintField;
                    if (!empty($sprintName)) {
                        if (!isset($sprintDistribution[$sprintName])) {
                            $sprintDistribution[$sprintName] = [
                                'tickets' => 0,
                                'points' => 0
                            ];
                        }
                        $sprintDistribution[$sprintName]['tickets']++;
                        $sprintDistribution[$sprintName]['points'] += $storyPoints;
                    } else {
                        $this->logger->info('Sprint field present but empty or unrecognized: ' . json_encode($sprintField));
                    }
                }
            }
        }

        $sprintCount = count($sprintDistribution);
        $avgTicketsPerSprint = $sprintCount > 0 ? round($totalTickets / $sprintCount, 1) : 0;
        $avgPointsPerSprint = $sprintCount > 0 ? round($totalPoints / $sprintCount, 1) : 0;

        return [
            'totalTickets' => $totalTickets,
            'totalPoints' => $totalPoints,
            'bugTasks' => $bugTasks,
            'avgTicketsPerSprint' => $avgTicketsPerSprint,
            'avgPointsPerSprint' => $avgPointsPerSprint,
            'typeDistribution' => $typeDistribution,
            'sprintDistribution' => $sprintDistribution,
            'sprintCount' => $sprintCount
        ];
    }

    /**
     * Calcule les statistiques de qualité
     */
    private function calculateQualityStats(array $issues): array
    {
        $qaReturns = 0; // nombre de Bug task validés
        $totalValidated = 0; // nombre de tickets validés (tous types)
        $qaReturnCounts = [];
        $statusCounts = [];
        $firstTimeValidations = 0; // pour le taux, mais non affiché
        $qaReturnCountsAll = [];

        // Statuts considérés comme "validés"
        $validatedStatuses = [
            'Done', 'Validé', 'Closed', 'Terminé', 'Resolved', 'Completed',
            'Fermé', 'Validated', 'Approved', 'Accepté', 'Accepté en recette'
        ];

        // Pour le taux de validation : tous les tickets validés
        foreach ($issues as $issue) {
            $status = $issue['fields']['status']['name'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            if (in_array($status, $validatedStatuses)) {
                $totalValidated++;
                // Pour la moyenne sur tous les tickets validés
                $qaReturnCountsAll[] = $this->countQAReturns($issue);
            }
        }

        // Pour les QA Returns : uniquement les Bug task validés
        foreach ($issues as $issue) {
            $status = $issue['fields']['status']['name'];
            $issueType = $issue['fields']['issuetype']['name'] ?? '';
            if ($issueType === 'Bug task' && in_array($status, $validatedStatuses)) {
                $qaReturns++; // on compte le nombre de bug task validés
                $qaReturnCount = $this->countQAReturns($issue);
                $qaReturnCounts[] = $qaReturnCount;
                if ($qaReturnCount === 0) {
                    $firstTimeValidations++;
                }
            }
        }

        $this->logger->info("Status distribution for quality analysis: " . json_encode($statusCounts));
        $this->logger->info("Total validated tickets: {$totalValidated}");
        $this->logger->info("Total validated Bug task: {$qaReturns}");

        // Nouveau calcul : % de bug task validés sur tous les tickets validés
        $validationRate = $totalValidated > 0 ? round(($qaReturns / $totalValidated) * 100, 1) : 0;
        $avgQAReturns = !empty($qaReturnCountsAll) ? round(array_sum($qaReturnCountsAll) / count($qaReturnCountsAll), 1) : 0;

        return [
            'qaReturns' => $qaReturns,
            'totalValidated' => $totalValidated,
            'validationRate' => $validationRate,
            'avgQAReturns' => $avgQAReturns,
            'qaReturnCounts' => $qaReturnCounts,
            'statusDistribution' => $statusCounts // Pour debug
        ];
    }

    /**
     * Calcule les statistiques d'impact
     */
    private function calculateImpactStats(array $issues): array
    {
        $epics = [];
        $strategicEpics = [];
        $biggestTickets = [];

        foreach ($issues as $issue) {
            $storyPoints = $this->getStoryPoints($issue);
            
            // Identifier les plus gros tickets
            if ($storyPoints >= 8) {
                $biggestTickets[] = [
                    'key' => $issue['key'],
                    'summary' => $issue['fields']['summary'],
                    'points' => $storyPoints,
                    'type' => $issue['fields']['issuetype']['name']
                ];
            }

            // Analyser les Epics
            if (isset($issue['fields']['epic'])) {
                $epicKey = $issue['fields']['epic']['key'];
                $epicName = $issue['fields']['epic']['name'];
                
                if (!isset($epics[$epicKey])) {
                    $epics[$epicKey] = [
                        'name' => $epicName,
                        'tickets' => 0,
                        'points' => 0
                    ];
                }
                
                $epics[$epicKey]['tickets']++;
                $epics[$epicKey]['points'] += $storyPoints;
                
                // Identifier les Epics stratégiques (basé sur les points ou labels)
                if ($storyPoints >= 5 || $this->isStrategicEpic($issue)) {
                    $strategicEpics[$epicKey] = $epics[$epicKey];
                }
            }
        }

        // Trier les plus gros tickets par points
        usort($biggestTickets, fn($a, $b) => $b['points'] <=> $a['points']);

        return [
            'epics' => $epics,
            'strategicEpics' => $strategicEpics,
            'biggestTickets' => array_slice($biggestTickets, 0, 10), // Top 10
            'epicCount' => count($epics),
            'strategicEpicCount' => count($strategicEpics)
        ];
    }

    /**
     * Calcule les statistiques de collaboration
     */
    private function calculateCollaborationStats(array $issues): array
    {
        $technicalTickets = 0;
        $crossTeamTickets = 0;

        foreach ($issues as $issue) {
            $issueType = $issue['fields']['issuetype']['name'];
            $labels = $issue['fields']['labels'] ?? [];
            
            if ($issueType === 'Technique') {
                $technicalTickets++;
            }
            
            if (in_array('cross-team', $labels) || in_array('collaboration', $labels)) {
                $crossTeamTickets++;
            }
        }

        return [
            'technicalTickets' => $technicalTickets,
            'crossTeamTickets' => $crossTeamTickets,
            'collaborationRate' => count($issues) > 0 ? round(($technicalTickets + $crossTeamTickets) / count($issues) * 100, 1) : 0
        ];
    }

    /**
     * Calcule les statistiques d'évolution
     */
    private function calculateEvolutionStats(array $issues): array
    {
        $monthlyStats = [];
        $complexityEvolution = [];

        foreach ($issues as $issue) {
            $created = new \DateTime($issue['fields']['created']);
            $month = $created->format('Y-m');
            $storyPoints = $this->getStoryPoints($issue);
            
            if (!isset($monthlyStats[$month])) {
                $monthlyStats[$month] = [
                    'tickets' => 0,
                    'points' => 0,
                    'avgComplexity' => 0
                ];
            }
            
            $monthlyStats[$month]['tickets']++;
            $monthlyStats[$month]['points'] += $storyPoints;
            
            $complexityEvolution[] = [
                'month' => $month,
                'points' => $storyPoints,
                'type' => $issue['fields']['issuetype']['name']
            ];
        }

        // Calculer la complexité moyenne par mois
        foreach ($monthlyStats as $month => &$stats) {
            $stats['avgComplexity'] = $stats['tickets'] > 0 ? round($stats['points'] / $stats['tickets'], 1) : 0;
        }

        return [
            'monthlyStats' => $monthlyStats,
            'complexityEvolution' => $complexityEvolution
        ];
    }

    /**
     * Extrait le feedback qualitatif
     */
    private function extractQualitativeFeedback(array $issues): array
    {
        $positiveFeedback = [];
        $keywords = ['merci', 'thanks', 'great', 'excellent', 'awesome', 'perfect', 'good job', 'bravo'];

        foreach ($issues as $issue) {
            // Analyser les commentaires
            if (isset($issue['fields']['comment']['comments'])) {
                foreach ($issue['fields']['comment']['comments'] as $comment) {
                    $body = strtolower($comment['body']);
                    
                    foreach ($keywords as $keyword) {
                        if (strpos($body, $keyword) !== false) {
                            $positiveFeedback[] = [
                                'issue' => $issue['key'],
                                'comment' => substr($comment['body'], 0, 200) . '...',
                                'author' => $comment['author']['displayName'],
                                'date' => $comment['created']
                            ];
                            break 2; // Un seul commentaire par ticket
                        }
                    }
                }
            }
        }

        return array_slice($positiveFeedback, 0, 5); // Top 5 feedbacks
    }

    /**
     * Récupère les story points d'un ticket
     */
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

    /**
     * Compte les retours QA dans le changelog
     */
    private function countQAReturns(array $issue): int
    {
        if (!isset($issue['changelog']['histories'])) {
            return 0;
        }

        $qaReturns = 0;
        foreach ($issue['changelog']['histories'] as $history) {
            if (!isset($history['items'])) {
                continue;
            }
            
            foreach ($history['items'] as $item) {
                if ($item['field'] === 'status') {
                    $fromStatus = $item['fromString'] ?? '';
                    $toStatus = $item['toString'] ?? '';
                    
                    // Détecter les retours QA (ex: Done -> En cours, Validé -> En cours)
                    if (in_array($fromStatus, ['Done', 'Validé']) && in_array($toStatus, ['En cours', 'In Progress', 'To Do'])) {
                        $qaReturns++;
                    }
                }
            }
        }

        return $qaReturns;
    }

    /**
     * Détermine si une Epic est stratégique
     */
    private function isStrategicEpic(array $issue): bool
    {
        $labels = $issue['fields']['labels'] ?? [];
        $priority = $issue['fields']['priority']['name'] ?? '';
        
        return in_array('strategic', $labels) || 
               in_array('epic', $labels) || 
               $priority === 'Highest' || 
               $priority === 'High';
    }

    /**
     * Exporte les statistiques en JSON
     */
    public function exportToJson(TeamMemberProfile $profile): string
    {
        $data = $profile->getAllStats();
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Retourne des statistiques de productivité vides
     */
    private function getEmptyProductivityStats(): array
    {
        return [
            'totalTickets' => 0,
            'totalPoints' => 0,
            'bugTasks' => 0,
            'avgTicketsPerSprint' => 0,
            'avgPointsPerSprint' => 0,
            'typeDistribution' => [],
            'sprintDistribution' => [],
            'sprintCount' => 0
        ];
    }

    /**
     * Retourne des statistiques de qualité vides
     */
    private function getEmptyQualityStats(): array
    {
        return [
            'qaReturns' => 0,
            'firstTimeValidations' => 0,
            'totalValidated' => 0,
            'validationRate' => 0,
            'avgQAReturns' => 0,
            'qaReturnCounts' => [],
            'statusDistribution' => [] // Ajouter pour le débogage
        ];
    }

    /**
     * Retourne des statistiques d'impact vides
     */
    private function getEmptyImpactStats(): array
    {
        return [
            'epics' => [],
            'strategicEpics' => [],
            'biggestTickets' => [],
            'epicCount' => 0,
            'strategicEpicCount' => 0
        ];
    }

    /**
     * Retourne des statistiques de collaboration vides
     */
    private function getEmptyCollaborationStats(): array
    {
        return [
            'technicalTickets' => 0,
            'crossTeamTickets' => 0,
            'collaborationRate' => 0
        ];
    }

    /**
     * Retourne des statistiques d'évolution vides
     */
    private function getEmptyEvolutionStats(): array
    {
        return [
            'monthlyStats' => [],
            'complexityEvolution' => []
        ];
    }

    /**
     * Calcule la productivité par sprint pour un membre d'équipe
     */
    public function getSprintsProductivityStats(string $jiraId, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): array
    {
        $sprints = $this->fetchSprintsFromJira($periodStart, $periodEnd);
        $sprintStats = [];
        $totalTickets = 0;
        $totalPoints = 0;
        $bugTasks = 0;
        $typeDistribution = [];

        foreach ($sprints as $sprint) {
            $sprintId = $sprint['id'];
            $sprintName = $sprint['name'];
            $issues = $this->fetchSprintIssuesForMember($sprintId, $jiraId);
            $tickets = count($issues);
            $points = 0;
            
            foreach ($issues as $issue) {
                $points += $this->getStoryPoints($issue);
                
                // Compter les types de tickets
                $issueType = $issue['fields']['issuetype']['name'];
                $typeDistribution[$issueType] = ($typeDistribution[$issueType] ?? 0) + 1;
                
                // Compter les bug tasks
                if ($issueType === 'Bug') {
                    $bugTasks++;
                }
            }
            
            $sprintStats[$sprintName] = [
                'tickets' => $tickets,
                'points' => $points
            ];
            $totalTickets += $tickets;
            $totalPoints += $points;
        }

        $sprintCount = count($sprintStats);
        $avgTicketsPerSprint = $sprintCount > 0 ? round($totalTickets / $sprintCount, 1) : 0;
        $avgPointsPerSprint = $sprintCount > 0 ? round($totalPoints / $sprintCount, 1) : 0;

        return [
            'sprintDistribution' => $sprintStats,
            'sprintCount' => $sprintCount,
            'avgTicketsPerSprint' => $avgTicketsPerSprint,
            'avgPointsPerSprint' => $avgPointsPerSprint,
            'totalTickets' => $totalTickets,
            'totalPoints' => $totalPoints,
            'bugTasks' => $bugTasks,
            'typeDistribution' => $typeDistribution
        ];
    }

    /**
     * Reset les données de profil pour tous les membres de l'équipe
     */
    public function resetAllProfiles(): int
    {
        $teamMembers = $this->teamMemberRepository->findAll();
        $resetCount = 0;

        foreach ($teamMembers as $teamMember) {
            $profiles = $this->profileRepository->findAllByTeamMember($teamMember->getId());
            foreach ($profiles as $profile) {
                $this->profileRepository->remove($profile);
                $resetCount++;
            }
        }

        $this->entityManager->flush();
        $this->logger->info("Reset {$resetCount} profiles for all team members");
        
        return $resetCount;
    }

    /**
     * Reset les données de profil pour un membre spécifique
     */
    public function resetMemberProfile(int $memberId): int
    {
        $profiles = $this->profileRepository->findAllByTeamMember($memberId);
        $resetCount = 0;

        foreach ($profiles as $profile) {
            $this->profileRepository->remove($profile);
            $resetCount++;
        }

        $this->entityManager->flush();
        $this->logger->info("Reset {$resetCount} profiles for team member ID {$memberId}");
        
        return $resetCount;
    }

    /**
     * Récupère la liste des sprints du board Jira sur une période donnée
     */
    private function fetchSprintsFromJira(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $allSprints = [];
        $startAt = 0;
        $isLastPage = false;
        $boardId = $this->jiraBoardId;
        while (!$isLastPage) {
            $response = $this->jiraClient->request('GET', "/rest/agile/1.0/board/{$boardId}/sprint", [
                'query' => [
                    'state' => 'active,closed',
                    'startAt' => $startAt,
                    'maxResults' => 50
                ]
            ]);
            $data = $response->toArray();
            $allSprints = array_merge($allSprints, $data['values']);
            $total = $data['total'];
            $startAt += 50;
            $isLastPage = $startAt >= $total;
        }
        // Filtrer par période
        return array_filter($allSprints, function ($sprint) use ($startDate, $endDate) {
            if (!isset($sprint['startDate'])) return false;
            try {
                $sprintStart = new \DateTimeImmutable($sprint['startDate']);
                if ($sprint['state'] === 'active') {
                    return $sprintStart >= $startDate;
                }
                if (!isset($sprint['endDate'])) return false;
                $sprintEnd = new \DateTimeImmutable($sprint['endDate']);
                return $sprintStart >= $startDate && $sprintEnd <= $endDate;
            } catch (\Exception $e) {
                return false;
            }
        });
    }

    /**
     * Récupère les tickets d'un membre pour un sprint donné
     */
    private function fetchSprintIssuesForMember(int $sprintId, string $jiraId): array
    {
        $allIssues = [];
        $startAt = 0;
        $isLastPage = false;
        while (!$isLastPage) {
            $jql = sprintf('assignee = "%s" AND sprint = %d', $jiraId, $sprintId);
            $response = $this->jiraClient->request('GET', '/rest/api/2/search', [
                'query' => [
                    'jql' => $jql,
                    'startAt' => $startAt,
                    'maxResults' => 50,
                    'fields' => [
                        'key',
                        'summary',
                        'issuetype',
                        'status',
                        'priority',
                        'customfield_10016',
                        'customfield_10026',
                        'customfield_10200',
                        'created',
                        'updated',
                        'resolutiondate',
                        'assignee',
                        'reporter',
                        'epic',
                        'labels',
                        'components',
                        'comment',
                        'changelog'
                    ]
                ]
            ]);
            $data = $response->toArray();
            $allIssues = array_merge($allIssues, $data['issues']);
            $total = $data['total'];
            $startAt += 50;
            $isLastPage = $startAt >= $total;
        }
        return $allIssues;
    }
} 