<?php

namespace App\Service;

use DateTime;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DeploymentFrequencyService
{
    private const MAX_RESULTS_PER_PAGE = 50;

    public function __construct(
        private readonly HttpClientInterface $jiraClient,
        private readonly string $jiraBoardId,
    ) {
    }

    public function getDeploymentData(DateTime $startDate, DateTime $endDate): array
    {
        $releases = $this->fetchReleases($startDate, $endDate);
        
        $monthlyData = [];
        $currentDate = clone $startDate;
        
        // Initialize monthly data
        while ($currentDate <= $endDate) {
            $monthKey = $currentDate->format('Y-m');
            $monthStart = (clone $currentDate)->modify('first day of this month')->format('Y-m-d');
            $monthEnd = (clone $currentDate)->modify('last day of this month')->format('Y-m-d');
            
            $monthlyData[$monthKey] = [
                'count' => 0,
                'month' => $currentDate->format('F Y'),
                'deployments' => [],
                'link' => sprintf(
                    'https://studi-pedago.atlassian.net/projects/MD/versions?status=released&releasedDateFrom=%s&releasedDateTo=%s',
                    $monthStart,
                    $monthEnd
                )
            ];
            $currentDate->modify('+1 month');
        }

        // Analyze releases
        foreach ($releases as $release) {
            if (!isset($release['released']) || !$release['released']) {
                continue;
            }
            
            // Vérifier si la date de release existe et est au bon format
            if (!isset($release['releaseDate'])) {
                continue;
            }
            try {
                $releaseDate = new DateTime($release['releaseDate']);
                $monthKey = $releaseDate->format('Y-m');
                
                if (!isset($monthlyData[$monthKey])) {
                    continue;
                }

                $monthlyData[$monthKey]['count']++;
                $monthlyData[$monthKey]['deployments'][] = [
                    'key' => $release['id'],
                    'summary' => $release['name'],
                    'date' => $releaseDate->format('Y-m-d H:i'),
                    'link' => sprintf('https://studi-pedago.atlassian.net/projects/MD/versions/%s', $release['id'])
                ];
            } catch (\Exception $e) {
                // Si la date n'est pas dans un format valide, on ignore cette release
                continue;
            }
        }
        // Calculate averages
        $totalMonths = count($monthlyData);
        $totalDeployments = 0;
        foreach ($monthlyData as $data) {
            $totalDeployments += $data['count'];
        }

        $averageDeployments = $totalMonths > 0 ? round($totalDeployments / $totalMonths, 1) : 0;

        return [
            'monthly' => array_values($monthlyData),
            'average' => $averageDeployments,
            'total' => $totalDeployments,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ];
    }

private function fetchReleases(DateTime $startDate, DateTime $endDate): array
{
    $response = $this->jiraClient->request('GET', "/rest/api/2/project/MD/versions");

    $allVersions = $response->toArray();

    $filteredVersions = array_filter($allVersions, function ($version) use ($startDate, $endDate) {
        if (empty($version['releaseDate'])) {
            return false;
        }

        $releaseDate = DateTime::createFromFormat('Y-m-d', $version['releaseDate']);
        return $releaseDate >= $startDate && $releaseDate <= $endDate;
    });

    return array_values($filteredVersions); // Reindexe le tableau
}

} 