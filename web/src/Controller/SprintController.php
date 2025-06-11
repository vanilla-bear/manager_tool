<?php

namespace App\Controller;

use App\Entity\Sprint;
use App\Repository\SprintRepository;
use App\Service\SprintSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/sprints')]
class SprintController extends AbstractController
{
    public function __construct(
        private readonly SprintRepository $sprintRepository,
        private readonly SprintSyncService $sprintSyncService
    ) {
    }

    #[Route('/', name: 'app_sprint_index', methods: ['GET'])]
    public function index(): Response
    {
        $sprints = $this->sprintRepository->findLastSprints(10);
        $averageVelocity = $this->sprintRepository->getAverageVelocity();

        return $this->render('sprint/index.html.twig', [
            'sprints' => $sprints,
            'averageVelocity' => $averageVelocity,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sprint_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Sprint $sprint): Response
    {
        if ($request->isMethod('POST')) {
            $plannedCapacity = $request->request->get('planned_capacity');
            if ($plannedCapacity !== null) {
                $sprint->setPlannedCapacityDays((float) $plannedCapacity);
                $this->sprintRepository->save($sprint, true);

                $this->addFlash('success', 'Sprint capacity updated successfully.');
                return $this->redirectToRoute('app_sprint_index');
            }
        }

        return $this->render('sprint/edit.html.twig', [
            'sprint' => $sprint,
        ]);
    }

    #[Route('/sync', name: 'app_sprint_sync', methods: ['GET', 'POST'])]
    public function sync(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $startDate = new \DateTime($request->request->get('start_date'));
            $endDate = new \DateTime($request->request->get('end_date'));
            //dd($startDate, $endDate);
            try {
                $syncedSprints = $this->sprintSyncService->synchronizeSprints($startDate, $endDate);
                $this->addFlash('success', sprintf('%d sprints synchronized successfully.', count($syncedSprints)));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error synchronizing sprints: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_sprint_index');
        }

        return $this->render('sprint/sync.html.twig');
    }

    #[Route('/velocity', name: 'app_sprint_velocity', methods: ['GET'])]
    public function velocity(): Response
    {
        $sprints = $this->sprintRepository->findLastSprints(10);
        return $this->render('sprint/velocity.html.twig', [
            'sprints' => $sprints,
            'averageVelocity' => $this->sprintRepository->getAverageVelocity(),
        ]);
    }
} 