<?php

namespace App\Controller;

use App\Repository\SprintRepository;
use App\Service\ThroughputService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/kpi/throughput')]
class ThroughputController extends AbstractController
{
    public function __construct(
        private readonly ThroughputService $throughputService,
        private readonly SprintRepository $sprintRepository
    ) {
    }

    #[Route('', name: 'app_throughput_index')]
    public function index(Request $request): Response
    {
        // Par défaut : utiliser une période qui couvre tous les sprints disponibles
        $allSprints = $this->sprintRepository->findLastSprints(20);
        if (!empty($allSprints)) {
            // Utiliser la période du premier au dernier sprint
            $startDate = $allSprints[count($allSprints) - 1]->getStartDate()->setTime(0, 0, 0);
            $endDate = $allSprints[0]->getEndDate()->setTime(23, 59, 59);
        } else {
            // Fallback si aucun sprint - utiliser 2025 par défaut
            $startDate = new \DateTime('2025-01-01');
            $endDate = new \DateTime('2025-12-31');
        }

        // Plage personnalisée si fournie
        if ($request->query->has('start') && $request->query->has('end')) {
            try {
                $startDate = new \DateTime($request->query->get('start'));
                $endDate = new \DateTime($request->query->get('end'));
    
                if ($endDate < $startDate) {
                    $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                    $startDate = (clone $now)->modify('first day of -1 months')->setTime(0, 0, 0);
                    $endDate = (clone $now)->setTime(23, 59, 59);;
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Format de date invalide. Utilisation de la période par défaut (3 derniers mois).');
            }
        }
    
        $data = $this->throughputService->getThroughputData($startDate, $endDate);
    
        return $this->render('throughput/index.html.twig', [
            'data' => $data,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
    
} 