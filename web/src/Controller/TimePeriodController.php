<?php

namespace App\Controller;

use App\Entity\TimePeriod;
use App\Form\TimePeriodEditType;
use App\Form\TimePeriodFilterType;
use App\Form\TimePeriodType;
use App\Repository\TimePeriodRepository;
use App\Service\DatePeriodService;
use App\Service\TimePeriodCapacityCalculator;
use App\Service\VelocityConfigService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TimePeriodController extends AbstractController {

  #[Route('/time-period', name: 'app_time_period_list')]
  public function index(Request $request, EntityManagerInterface $entityManager, DatePeriodService $datePeriodService, TimePeriodRepository $timePeriodRepository): Response {
    // Créer le formulaire d'ajout
    $timePeriod = new TimePeriod();
    $form = $this->createForm(TimePeriodType::class, $timePeriod, [
      'attr' => ['name' => 'create_form'] // Nom unique pour le formulaire d'ajout
    ]);

    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // Calculer les jours ouvrés et enregistrer la période
      $workingDays = $datePeriodService->calculateWorkingDays($timePeriod->getStartDate(), $timePeriod->getEndDate());
      $timePeriod->setWorkingDays($workingDays);

      $entityManager->persist($timePeriod);
      $entityManager->flush();

      // Rediriger pour éviter la resoumission du formulaire
      return $this->redirectToRoute('app_time_period_list');
    }

    // Créer le formulaire de filtre
    $filterForm = $this->createForm(TimePeriodFilterType::class, null, [
      'method' => 'GET',
      'attr' => ['name' => 'filter_form'] // Nom unique pour le formulaire de filtre
    ]);

    $filterForm->handleRequest($request);

    // Initialiser les critères de filtre
    $criteria = [];
    if ($filterForm->isSubmitted() && $filterForm->isValid()) {
      $data = $filterForm->getData();
      if ($data['name']) {
        $criteria['name'] = $data['name'];
      }
      if ($data['type']) {
        $criteria['type'] = $data['type'];
      }
    }

    // Récupérer les périodes de temps en fonction des critères de filtre
    $timePeriods = $timePeriodRepository->findByCriteria($criteria);

    return $this->render('time_period/index.html.twig', [
      'form' => $form->createView(),
      'filterForm' => $filterForm->createView(),
      'timePeriods' => $timePeriods,
    ]);
  }

  #[Route('/time-period/{id}', name: 'app_time_period_view', methods: ['GET'])]
  public function view(int $id, EntityManagerInterface $entityManager): Response
  {
    // Récupérer l'entité TimePeriod par son ID
    $timePeriod = $entityManager->getRepository(TimePeriod::class)->find($id);

    // Vérifier si l'entité existe
    if (!$timePeriod) {
      throw $this->createNotFoundException('Time period not found');
    }

    // Rendre la vue et passer l'entité au template
    return $this->render('time_period/view.html.twig', [
      'timePeriod' => $timePeriod,
    ]);
  }

  #[Route('/time-period/{id}/edit', name: 'app_time_period_edit')]
  public function edit(int $id, Request $request, EntityManagerInterface $entityManager, DatePeriodService $datePeriodService): Response
  {
    $timePeriod = $entityManager->getRepository(TimePeriod::class)->find($id);
    if (!$timePeriod) {
      throw $this->createNotFoundException('Time period not found');
    }

    $form = $this->createForm(TimePeriodEditType::class, $timePeriod);
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      $workingDays = $datePeriodService->calculateWorkingDays($timePeriod->getStartDate(),$timePeriod->getEndDate()); // Vous pouvez passer un tableau de jours fériés si nécessaire
      $timePeriod->setWorkingDays($workingDays);
      $entityManager->flush();
      return $this->redirectToRoute('app_time_period_list');
    }

    return $this->render('time_period/edit.html.twig', [
      'form' => $form->createView(),
    ]);
  }

  #[Route('/time-period/{id}/delete', name: 'app_time_period_delete', methods: ['POST'])]
  public function delete(int $id, EntityManagerInterface $entityManager, Request $request): Response
  {
    $timePeriod = $entityManager->getRepository(TimePeriod::class)->find($id);
    if (!$timePeriod) {
      throw $this->createNotFoundException('Time period not found');
    }

    if ($this->isCsrfTokenValid('delete' . $timePeriod->getId(), $request->request->get('_token'))) {
      $entityManager->remove($timePeriod);
      $entityManager->flush();
    }

    return $this->redirectToRoute('app_time_period_list');
  }

  #[Route('/time-period/{id}/calculate-capacity', name: 'app_time_period_calculate_capacity', methods: ['POST'])]
  public function calculateCapacity(
    int $id,
    EntityManagerInterface $entityManager,
    TimePeriodCapacityCalculator $calculator,
    DatePeriodService $datePeriodService,
    VelocityConfigService $velocityConfigService
  ): Response
  {
    // Récupérer l'entité TimePeriod par son ID
    $timePeriod = $entityManager->getRepository(TimePeriod::class)->find($id);

    // Vérifier si l'entité existe
    if (!$timePeriod) {
      throw $this->createNotFoundException('Time period not found');
    }

    // Appeler la méthode calculateCapacity sur le service
    $capacity = $calculator->calculateCapacity($timePeriod);
    $timePeriod->setCapacityData($capacity);

    // Calcul velocité
    $sprintDuration = $datePeriodService->calculateWorkingDays($timePeriod->getStartDate(),$timePeriod->getEndDate(),false); // Vous pouvez passer un tableau de jours fériés si nécessaire
    $globalVelocity = $velocityConfigService->getVelocity();
    $velocity = $calculator->calculateVelocity($capacity,$globalVelocity,$sprintDuration);

    // Extraire la vélocité ajustée pour la sauvegarder
    $adjustedVelocity = $velocity['adjusted_velocity'];

    // Mettre à jour le champ estimatedVelocity de l'entité TimePeriod
    $timePeriod->setEstimatedVelocity($adjustedVelocity);

    // Enregistrer les modifications en base de données
    $entityManager->persist($timePeriod);
    $entityManager->flush();

    // Ajouter un message flash pour afficher le résultat
//    $this->addFlash('success', 'La capacité calculée est de : ' . $capacity);

    // Rediriger vers la vue de la période de temps avec le message flash
    return $this->redirectToRoute('app_time_period_view', ['id' => $id]);
  }

}
