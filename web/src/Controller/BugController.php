<?php

namespace App\Controller;

use App\Repository\MonthlyStatsRepository;
use App\Repository\SprintRepository;
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
        private readonly BugStatsService $bugStatsService,
        private readonly SprintRepository $sprintRepository
    ) {
    }

    #[Route('/', name: 'app_bugs_index', methods: ['GET'])]
    public function index(): Response
    {
        // Utiliser une période qui couvre tous les sprints disponibles
        $allSprints = $this->sprintRepository->findLastSprints(20);
        if (!empty($allSprints)) {
            $startDate = $allSprints[count($allSprints) - 1]->getStartDate()->setTime(0, 0, 0);
            $endDate = $allSprints[0]->getEndDate()->setTime(23, 59, 59);
        } else {
            $startDate = new \DateTime('2025-01-01');
            $endDate = new \DateTime('2025-12-31');
        }

        $sprintBugStats = $this->bugStatsService->getSprintBugStats($startDate, $endDate);

        return $this->render('bug/sprint_index.html.twig', [
            'sprintStats' => $sprintBugStats,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ]
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