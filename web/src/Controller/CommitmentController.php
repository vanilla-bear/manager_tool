<?php

namespace App\Controller;

use App\Repository\SprintRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/commitment')]
class CommitmentController extends AbstractController
{
    public function __construct(
        private readonly SprintRepository $sprintRepository,
    ) {
    }

    #[Route('/', name: 'app_commitment_index', methods: ['GET'])]
    public function index(): Response
    {
        $sprints = $this->sprintRepository->findLastSprints(10);
        
        // Calculer la moyenne glissante sur 3 sprints
        $averageCompletionRate = 0;
        $sprintCount = 0;
        $lastThreeSprints = array_slice($sprints, 0, 3);
        
        foreach ($lastThreeSprints as $sprint) {
            $rate = $sprint->getCompletionRate();
            if ($rate !== null) {
                $averageCompletionRate += $rate;
                $sprintCount++;
            }
        }
        
        if ($sprintCount > 0) {
            $averageCompletionRate /= $sprintCount;
        }

        return $this->render('commitment/index.html.twig', [
            'sprints' => $sprints,
            'averageCompletionRate' => $averageCompletionRate,
        ]);
    }
} 