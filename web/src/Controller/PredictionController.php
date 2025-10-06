<?php

namespace App\Controller;

use App\Service\SimpleVelocityPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/predictions')]
class PredictionController extends AbstractController
{
    public function __construct(
        private readonly SimpleVelocityPredictionService $predictionService
    ) {
    }

    #[Route('/', name: 'app_prediction_index', methods: ['GET'])]
    public function index(): Response
    {
        try {
            $velocityPrediction = $this->predictionService->predictNextSprintVelocity();
            $completionPrediction = $this->predictionService->predictSprintCompletion();
            $riskFactors = $this->predictionService->identifyRiskFactors();

            return $this->render('prediction/index.html.twig', [
                'velocity_prediction' => $velocityPrediction,
                'completion_prediction' => $completionPrediction,
                'risk_factors' => $riskFactors,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du calcul des prédictions: ' . $e->getMessage());
            return $this->render('prediction/index.html.twig', [
                'velocity_prediction' => null,
                'completion_prediction' => null,
                'risk_factors' => [],
            ]);
        }
    }

    #[Route('/velocity', name: 'app_prediction_velocity', methods: ['GET'])]
    public function velocity(): JsonResponse
    {
        try {
            $prediction = $this->predictionService->predictNextSprintVelocity();
            return new JsonResponse($prediction);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du calcul de la prédiction de vélocité',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/completion', name: 'app_prediction_completion', methods: ['GET'])]
    public function completion(): JsonResponse
    {
        try {
            $prediction = $this->predictionService->predictSprintCompletion();
            return new JsonResponse($prediction);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors du calcul de la prédiction de réussite',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/risks', name: 'app_prediction_risks', methods: ['GET'])]
    public function risks(): JsonResponse
    {
        try {
            $riskFactors = $this->predictionService->identifyRiskFactors();
            return new JsonResponse($riskFactors);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'identification des facteurs de risque',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/dashboard', name: 'app_prediction_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        try {
            $velocityPrediction = $this->predictionService->predictNextSprintVelocity();
            $completionPrediction = $this->predictionService->predictSprintCompletion();
            $riskFactors = $this->predictionService->identifyRiskFactors();

            return $this->render('prediction/dashboard.html.twig', [
                'velocity_prediction' => $velocityPrediction,
                'completion_prediction' => $completionPrediction,
                'risk_factors' => $riskFactors,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du chargement du dashboard de prédiction: ' . $e->getMessage());
            return $this->render('prediction/dashboard.html.twig', [
                'velocity_prediction' => null,
                'completion_prediction' => null,
                'risk_factors' => [],
            ]);
        }
    }

    #[Route('/debug', name: 'app_prediction_debug', methods: ['GET'])]
    public function debug(): Response
    {
        try {
            $velocityPrediction = $this->predictionService->predictNextSprintVelocity();

            return $this->render('prediction/debug.html.twig', [
                'velocity_prediction' => $velocityPrediction,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du chargement du debug: ' . $e->getMessage());
            return $this->render('prediction/debug.html.twig', [
                'velocity_prediction' => null,
            ]);
        }
    }
}
