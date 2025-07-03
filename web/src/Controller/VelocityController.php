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
    // Charge la valeur de vélocité et l'explication depuis le fichier YAML
    $defaultVelocity = $this->velocityConfigService->getVelocity();
    $defaultExplanation = $this->velocityConfigService->getExplanation();

    // Création du formulaire avec les valeurs par défaut
    $form = $this->createForm(VelocityType::class, [
      'velocity' => $defaultVelocity,
      'explanation' => $defaultExplanation
    ]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $velocity = $form->get('velocity')->getData();
      $explanation = $form->get('explanation')->getData();

      // Met à jour la vélocité et l'explication dans le fichier YAML
      $this->velocityConfigService->setVelocityAndExplanation($velocity, $explanation);

      $this->addFlash('success', 'La vélocité et son explication ont été mises à jour.');

      // Redirige ou recharge la page après la soumission
      return $this->redirectToRoute('config_velocity');
    }

    return $this->render('velocity/configure.html.twig', [
      'form' => $form->createView(),
      'currentExplanation' => $defaultExplanation,
    ]);
  }
}
