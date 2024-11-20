<?php

namespace App\Controller;

use App\Form\VelocityType;
use App\Service\VelocityConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VelocityController extends AbstractController
{
  private VelocityConfigService $velocityConfigService;

  public function __construct(VelocityConfigService $velocityConfigService)
  {
    $this->velocityConfigService = $velocityConfigService;
  }

  #[Route('/config/velocity', name: 'config_velocity')]
  public function configureVelocity(Request $request): Response
  {
    // Charge la valeur de vélocité depuis le fichier YAML
    $defaultVelocity = $this->velocityConfigService->getVelocity();

    // Création du formulaire avec la valeur de vélocité par défaut
    $form = $this->createForm(VelocityType::class, ['velocity' => $defaultVelocity]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $velocity = $form->get('velocity')->getData();

      // Met à jour la vélocité dans le fichier YAML
      $this->velocityConfigService->setVelocity($velocity);

      $this->addFlash('success', 'La vélocité a été mise à jour.');

      // Redirige ou recharge la page après la soumission
      return $this->redirectToRoute('config_velocity');
    }

    return $this->render('velocity/configure.html.twig', [
      'form' => $form->createView(),
    ]);
  }
}
