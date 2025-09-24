<?php

namespace App\Controller;

use App\Service\BugStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/debug')]
class DebugController extends AbstractController
{
    public function __construct(
        private readonly BugStatsService $bugStatsService
    ) {
    }

    #[Route('/bugs', name: 'debug_bugs', methods: ['GET'])]
    public function debugBugs(Request $request): JsonResponse
    {
        $startDate = new \DateTime($request->query->get('start', '2025-07-01'));
        $endDate = new \DateTime($request->query->get('end', '2025-07-31'));
        
        $debug = [
            'parameters' => [
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ],
            'tests' => []
        ];
        
        try {
            // Test 1: Récupérer la JQL
            $jql = $this->bugStatsService->getBugsJQLForMonth($startDate);
            $debug['tests']['jql_query'] = $jql;
            
            // Test 2: Synchroniser un mois
            $stats = $this->bugStatsService->synchronizeMonth($startDate);
            $debug['tests']['synchronization'] = [
                'success' => true,
                'bugsCount' => $stats->getBugsCount(),
                'deliveredTicketsCount' => $stats->getDeliveredTicketsCount(),
                'syncedAt' => $stats->getSyncedAt()->format('Y-m-d H:i:s'),
            ];
            
        } catch (\Exception $e) {
            $debug['tests']['error'] = [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
        
        return new JsonResponse($debug, 200, [], JSON_PRETTY_PRINT);
    }
}
