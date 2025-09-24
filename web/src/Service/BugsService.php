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
        $this->logger->info('BugsService: Starting getBugsData', [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d')
        ]);
        
        $bugs = $this->fetchBugs($startDate, $endDate);
        $this->logger->info('BugsService: Fetched bugs count', ['count' => count($bugs)]);
        
        $monthlyBugs = [];
        $priorityStats = [
            'highest' => 0,
            'high'    => 0,
            'medium'  => 0,
            'low'     => 0,
            'lowest'  => 0,
        ];
    
        foreach ($bugs as $bug) {
            $fields = $bug['fields'] ?? [];
            $createdRaw = $fields['created'] ?? null;
            $createdDate = $createdRaw ? new DateTime($createdRaw) : null;
            $monthKey = $createdDate ? $createdDate->format('Y-m') : 'unknown';
    
            if (!isset($monthlyBugs[$monthKey])) {
                $monthlyBugs[$monthKey] = [
                    'month' => $monthKey,
                    'count' => 0,
                    'bugs'  => [],
                ];
            }
    
            $priorityName = strtolower($fields['priority']['name'] ?? '');
            if (isset($priorityStats[$priorityName])) {
                $priorityStats[$priorityName]++;
            }
    
            $monthlyBugs[$monthKey]['count']++;
            $monthlyBugs[$monthKey]['bugs'][] = [
                'key'     => $bug['key'] ?? '',
                'summary' => $fields['summary'] ?? '',
                'created' => $createdDate ? $createdDate->format('Y-m-d H:i') : null,
                'status'  => $fields['status']['name'] ?? '',
                'priority'=> $fields['priority']['name'] ?? '',
                'link'    => isset($bug['key'])
                    ? sprintf('https://studi-pedago.atlassian.net/browse/%s', $bug['key'])
                    : null,
            ];
        }
    
        // Calcul du taux avec un total fiable
        $totalTickets = $this->getTotalTickets($startDate, $endDate);
        $bugRate = $totalTickets > 0 ? round((count($bugs) / $totalTickets) * 100, 1) : 0.0;
    
        // On renvoie un array indexé pour la sérialisation, mais chaque entrée garde 'month'
        $monthly = array_values($monthlyBugs);
    
        return [
            'total_bugs'            => count($bugs),
            'bug_rate'              => $bugRate,
            'priority_distribution' => $priorityStats,
            'monthly_data'          => $monthly,
            'bugs'                  => $bugs,
        ];
    }
    

    private function fetchBugs(DateTime $startDate, DateTime $endDate): array
    {
        $allBugs = [];
        
        $jql = sprintf(
            'project = "MD" AND issuetype = "Bug" AND created >= "%s" AND created <= "%s" ORDER BY created ASC',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
        
        $this->logger->info('BugsService: JQL Query', ['jql' => $jql]);
        
        $maxResults = 100; // autorisé par la v3
        $fieldsCsv = 'key,summary,created,resolutiondate,status,priority'; // CSV, pas tableau
        
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
                
                $this->logger->info('BugsService: API Response', [
                    'issuesCount' => count($data['issues'] ?? []),
                    'nextPageToken' => $data['nextPageToken'] ?? null
                ]);
                
                // la réponse enhanced n'a pas "total" ; on lit les issues et le nextPageToken
                $issues = $data['issues'] ?? [];
                foreach ($issues as $issue) {
                    $allBugs[] = $issue; // on a déjà les champs utiles
                }
                
                $nextPageToken = $data['nextPageToken'] ?? null;
                $pageSafeguard++;
                
            } while ($nextPageToken !== null && $pageSafeguard < $pageLimit);
            
            if ($pageSafeguard >= $pageLimit) {
                error_log('WARN: Pagination interrompue (limite de sécurité atteinte).');
            }
            
            // Succès : on a tout récupéré via /search/jql ; on retourne.
            return $allBugs;
            
        } catch (\Throwable $t) {
            // Fallback legacy si /search/jql n'est pas dispo sur l'instance (ou feature flag)
            $this->logger->warning('BugsService: Enhanced search failed, falling back to legacy', [
                'error' => $t->getMessage()
            ]);
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
                    'fields'     => ['key', 'summary', 'created', 'resolutiondate', 'status', 'priority'],
                ],
            ]);
            $data = $response->toArray(false);
            
            $issues = $data['issues'] ?? [];
            foreach ($issues as $issue) {
                $allBugs[] = $issue;
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
        
        return $allBugs;
    }

    private function getTotalTickets(DateTime $startDate, DateTime $endDate): int
    {
        $jql = sprintf(
            'project = "MD" AND created >= "%s" AND created <= "%s"',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
    
        // 1) Essai avec /rest/api/3/search (legacy) pour obtenir 'total' en un appel
        try {
            $response = $this->jiraClient->request('POST', '/rest/api/3/search', [
                'json' => [
                    'jql'        => $jql,
                    'maxResults' => 0,      // pas besoin des issues
                    'fields'     => ['key'], // champs minimaux
                ],
            ]);
            $data = $response->toArray(false);
            if (isset($data['total'])) {
                return (int) $data['total'];
            }
        } catch (\Throwable $e) {
            $this->logger->info('Classic search total not available: ' . $e->getMessage());
        }
    
        // 2) Fallback: Enhanced Search (/search/jql), somme du nombre d’issues par page
        try {
            $total = 0;
            $nextPageToken = null;
            $pageSafeguard = 0;
            $pageLimit = 200;   // garde-fou
            $maxResults = 100;  // page size
    
            do {
                $query = [
                    'jql'        => $jql,
                    'maxResults' => $maxResults,
                    'fields'     => 'key',
                ];
                if ($nextPageToken) {
                    $query['nextPageToken'] = $nextPageToken;
                }
    
                $resp = $this->jiraClient->request('GET', '/rest/api/3/search/jql', ['query' => $query]);
                $data = $resp->toArray(false);
    
                $batch = $data['issues'] ?? [];
                $count = is_array($batch) ? count($batch) : 0;
                $total += $count;
    
                $nextPageToken = $data['nextPageToken'] ?? null;
    
                // n'incrémente le safeguard que si on a réellement parcouru une page
                if ($count > 0) {
                    $pageSafeguard++;
                }
            } while ($nextPageToken !== null && $pageSafeguard < $pageLimit);
    
            return $total;
        } catch (\Throwable $e2) {
            $this->logger->warning('Unable to compute total via enhanced search: ' . $e2->getMessage());
        }
    
        // 3) Dernier recours : zéro (évite les estimations trompeuses)
        return 0;
    }
    
} 