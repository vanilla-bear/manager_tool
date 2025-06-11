<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use DateTimeImmutable;

class MTTRService
{
    private const POST_DEV_STATUSES = [
        'A faire',
        'Devs Terminés',
        'Test PO',
        'Finalisé',
        'INTEGRATION',
        'OK pour MEP',
        'Terminé',
        'Annulé'
    ];

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly string $jiraBoardId,
    ) {
    }

    public function getBugMTTRStats(\DateTimeInterface $startDate, \DateTimeInterface $endDate, ?string $lastBugKey = null): array
    {
        $boardInfo = $this->getBoardProject();
        $jql = $this->buildMTTRJQL($boardInfo['projectKey'], $startDate, $endDate, $lastBugKey);
        
        return $this->fetchBugsWithTransitions($jql);
    }

    private function buildMTTRJQL(string $projectKey, \DateTimeInterface $startDate, \DateTimeInterface $endDate, ?string $lastBugKey = null): string
    {
        $conditions = [
            sprintf('project = "%s"', $projectKey),
            'issuetype = Bug',
        ];

        if ($lastBugKey) {
            $conditions[] = sprintf('id > "%s"', $lastBugKey);
        } else {
            $conditions[] = sprintf('created >= "%s"', $startDate->format('Y-m-d'));
            $conditions[] = sprintf('created <= "%s"', $endDate->format('Y-m-d'));
        }

        return implode(' AND ', $conditions) . ' ORDER BY created ASC';
    }

    private function fetchBugsWithTransitions(string $jql): array
    {
        $bugs = [];
        $startAt = 0;
        $maxResults = 50;

        do {
            $response = $this->jiraClient->request('GET', '/rest/api/2/search', [
                'query' => [
                    'jql' => $jql,
                    'startAt' => $startAt,
                    'maxResults' => $maxResults,
                    'fields' => 'key,created,status,summary'
                ]
            ]);

            $data = $response->toArray();
            
            foreach ($data['issues'] as $issue) {
                $transitions = $this->getIssueTransitions($issue['key']);
                $mttrStats = $this->calculateMTTRStats($transitions, $issue['fields']['created']);
                
                $bugs[] = [
                    'key' => $issue['key'],
                    'summary' => $issue['fields']['summary'],
                    'created' => $issue['fields']['created'],
                    'currentStatus' => $issue['fields']['status']['name'],
                    'mttrStats' => $mttrStats
                ];
            }

            $startAt += $maxResults;
        } while ($startAt < $data['total']);

        return $bugs;
    }

    private function getIssueTransitions(string $issueKey): array
    {
        $response = $this->jiraClient->request('GET', "/rest/api/2/issue/{$issueKey}/changelog");
        $data = $response->toArray();
        
        $transitions = [];
        foreach ($data['values'] as $history) {
            foreach ($history['items'] as $item) {
                if ($item['field'] === 'status') {
                    $transitions[] = [
                        'fromStatus' => $item['fromString'],
                        'toStatus' => $item['toString'],
                        'created' => $history['created']
                    ];
                }
            }
        }

        return $transitions;
    }

    private function calculateMTTRStats(array $transitions, string $created): array
    {
        $stats = [
            'createdToTermine' => null,
            'aFaireToTermine' => null,
            'aFaireToDevsTermines' => null
        ];

        $statusDates = [
            'created' => new DateTimeImmutable($created),
            'A faire' => null,
            'Devs Terminés' => null,
            'Terminé' => null
        ];

        // Parcourir les transitions pour trouver les dates des statuts qui nous intéressent
        foreach ($transitions as $transition) {
            if ($transition['toStatus'] === 'A faire') {
                $statusDates['A faire'] = new DateTimeImmutable($transition['created']);
            }
            if ($transition['toStatus'] === 'Devs Terminés') {
                $statusDates['Devs Terminés'] = new DateTimeImmutable($transition['created']);
            }
            if ($transition['toStatus'] === 'Terminé') {
                $statusDates['Terminé'] = new DateTimeImmutable($transition['created']);
            }
        }

        // Calculer les différentes durées
        if ($statusDates['Terminé']) {
            $stats['createdToTermine'] = $statusDates['Terminé']->getTimestamp() - $statusDates['created']->getTimestamp();
            
            if ($statusDates['A faire']) {
                $stats['aFaireToTermine'] = $statusDates['Terminé']->getTimestamp() - $statusDates['A faire']->getTimestamp();
                
                if ($statusDates['Devs Terminés']) {
                    $stats['aFaireToDevsTermines'] = $statusDates['Devs Terminés']->getTimestamp() - $statusDates['A faire']->getTimestamp();
                }
            }
        }

        return $stats;
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
} 