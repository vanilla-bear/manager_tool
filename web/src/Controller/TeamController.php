<?php

namespace App\Controller;

use App\Entity\TeamMember;
use App\Form\TeamMemberType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TeamController extends AbstractController
{
  #[Route('/team', name: 'app_team')]
  public function index(Request $request, EntityManagerInterface $entityManager): Response {
    // Récupérer les membres de l'équipe
    $teamMembers = $entityManager->getRepository(TeamMember::class)->findAll();

    // Créer le formulaire d'ajout
    $teamMember = new TeamMember();
    $form = $this->createForm(TeamMemberType::class, $teamMember);

    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $entityManager->persist($teamMember);
      $entityManager->flush();

      // Rediriger pour éviter la resoumission du formulaire
      return $this->redirectToRoute('app_team');
    }

    return $this->render('team/index.html.twig', [
      'form' => $form->createView(),
      'teamMembers' => $teamMembers,
    ]);
  }

  #[Route('/team/update-name/{id}', name: 'app_team_update_name', methods: ['POST'])]
  public function updateName(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
  {
    $teamMember = $entityManager->getRepository(TeamMember::class)->find($id);

    if (!$teamMember) {
      return new JsonResponse(['success' => false, 'message' => 'Team member not found'], 404);
    }

    $data = json_decode($request->getContent(), true);
    if (isset($data['name']) && !empty($data['name'])) {
      $teamMember->setName($data['name']);
      $entityManager->flush();
      return new JsonResponse(['success' => true]);
    }

    return new JsonResponse(['success' => false, 'message' => 'Invalid name'], 400);
  }

  #[Route('/team/{id}/delete', name: 'app_team_delete', methods: ['POST'])]
  public function delete(int $id, EntityManagerInterface $entityManager, Request $request): Response
  {
    $teamMember = $entityManager->getRepository(TeamMember::class)->find($id);
    if (!$teamMember) {
      throw $this->createNotFoundException('Team member not found');
    }

    if ($this->isCsrfTokenValid('delete' . $teamMember->getId(), $request->request->get('_token'))) {
      $entityManager->remove($teamMember);
      $entityManager->flush();
    }

    return $this->redirectToRoute('app_team');
  }

}
