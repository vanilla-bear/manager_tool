<?php

namespace App\Controller;

use App\Service\DeploymentFrequencyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/kpi/deployment-frequency')]
class DeploymentFrequencyController extends AbstractController
{
    public function __construct(
        private readonly DeploymentFrequencyService $deploymentFrequencyService
    ) {
    }

    #[Route('', name: 'app_deployment_frequency_index')]
    public function index(Request $request): Response
    {
        // Par défaut : du 1er jour du mois 2 mois avant le mois courant au dernier jour du mois courant
        $now = new \DateTime();
        $startDate = (clone $now)->modify('first day of -1 months')->setTime(0, 0, 0);
        $endDate = (clone $now)->setTime(23, 59, 59);

        // Plage personnalisée si fournie
        if ($request->query->has('start') && $request->query->has('end')) {
            try {
                $startDate = new \DateTime($request->query->get('start'));
                $endDate = new \DateTime($request->query->get('end'));
                
                if ($endDate < $startDate) {
                    $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                    $startDate = (clone $now)->modify('first day of -1 months')->setTime(0, 0, 0);
                    $endDate = (clone $now)->setTime(23, 59, 59);
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Format de date invalide. Utilisation de la période par défaut (3 derniers mois).');
            }
        }

        $data = $this->deploymentFrequencyService->getDeploymentData($startDate, $endDate);

        return $this->render('deployment_frequency/index.html.twig', [
            'data' => $data,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }
} 