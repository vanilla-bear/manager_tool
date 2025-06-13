<?php

namespace App\Controller;

use App\Service\BlockersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/kpi/blockers')]
class BlockersController extends AbstractController
{
    public function __construct(
        private readonly BlockersService $blockersService
    ) {
    }

    #[Route('', name: 'app_blockers_index')]
    public function index(Request $request): Response
    {
        // Période par défaut : du 1er jour du mois précédent au dernier jour du mois courant
        $now = new \DateTime();
        $startDate = (clone $now)->modify('first day of -1 months')->setTime(0, 0, 0);
        $endDate = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);

        // Si une plage personnalisée est passée en GET (?start=...&end=...)
        if ($request->query->has('start') && $request->query->has('end')) {
            try {
                $startDate = new \DateTime($request->query->get('start'));
                $endDate = new \DateTime($request->query->get('end'));

                if ($endDate < $startDate) {
                    $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                    $startDate = (clone $now)->modify('first day of -1 months')->setTime(0, 0, 0);
                    $endDate = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Format de date invalide. Utilisation de la période par défaut.');
            }
        }

        $data = $this->blockersService->getBlockersData($startDate, $endDate);

        return $this->render('blockers/index.html.twig', [
            'data' => $data,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    #[Route('/current-sprint', name: 'app_blockers_current_sprint')]
    public function currentSprint(): Response
    {
        $data = $this->blockersService->getCurrentSprintBlockers();

        return $this->render('blockers/current_sprint.html.twig', [
            'data' => $data
        ]);
    }
}
