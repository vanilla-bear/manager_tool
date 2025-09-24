<?php

namespace App\Controller;

use App\Repository\MonthlyStatsRepository;
use App\Service\BugStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/bugs')]
class BugController extends AbstractController
{
    public function __construct(
        private readonly MonthlyStatsRepository $monthlyStatsRepository,
        private readonly BugStatsService $bugStatsService
    ) {
    }

    #[Route('/', name: 'app_bugs_index', methods: ['GET'])]
    public function index(): Response
    {
        $stats = $this->monthlyStatsRepository->findLastMonths(3);
        $averageBugRate = $this->monthlyStatsRepository->getAverageBugRate(3);
        $averageMonthlyBugs = $this->monthlyStatsRepository->getAverageMonthlyBugs(3);

        // Get JQL queries for each month
        $jqlQueries = [];
        foreach ($stats as $stat) {
            $jqlQueries[$stat->getMonth()->format('Y-m')] = $this->bugStatsService->getBugsJQLForMonth($stat->getMonth());
        }

        return $this->render('bug/index.html.twig', [
            'stats' => $stats,
            'averageBugRate' => $averageBugRate,
            'averageMonthlyBugs' => $averageMonthlyBugs,
            'jqlQueries' => $jqlQueries,
        ]);
    }

    #[Route('/sync', name: 'app_bugs_sync', methods: ['GET', 'POST'])]
    public function sync(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $startDate = new \DateTime($request->request->get('start_date'));
            $endDate = new \DateTime($request->request->get('end_date'));
            
            // Sauvegarder les dates dans la session pour la prochaine fois
            $request->getSession()->set('bug_sync_start_date', $startDate->format('Y-m-d'));
            $request->getSession()->set('bug_sync_end_date', $endDate->format('Y-m-d'));
            
            try {
                $currentDate = clone $startDate;
                $syncedMonths = 0;
                
                while ($currentDate <= $endDate) {
                    $this->bugStatsService->synchronizeMonth($currentDate);
                    $currentDate->modify('first day of next month');
                    $syncedMonths++;
                }

                $this->addFlash('success', sprintf('%d months synchronized successfully.', $syncedMonths));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error synchronizing bug statistics: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_bugs_index');
        }

        // Récupérer les dernières dates utilisées depuis la session
        $session = $request->getSession();
        $lastStartDate = $session->get('bug_sync_start_date');
        $lastEndDate = $session->get('bug_sync_end_date');

        return $this->render('bug/sync.html.twig', [
            'lastStartDate' => $lastStartDate,
            'lastEndDate' => $lastEndDate,
        ]);
    }
} 