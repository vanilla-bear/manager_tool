<?php

namespace App\Controller;

use App\Form\ConfigDatesType;
use App\Repository\GoogleCalendarEventRepository;
use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GoogleCalendarEventController extends AbstractController {

  private GoogleCalendarService $configDatesService;

  public function __construct(GoogleCalendarService $configDatesService)
  {
    $this->configDatesService = $configDatesService;
  }

  #[Route('/google-calendar-events', name: 'app_google_calendar')]
  public function index(Request $request, GoogleCalendarEventRepository $repository): Response {
    // Créer le formulaire de configuration des dates
    $datesConfigCalendar = $this->configDatesService->getDates();

    // Créer le formulaire avec les valeurs par défaut
    $form = $this->createForm(ConfigDatesType::class, [
      'date_debut' => $datesConfigCalendar['date_debut'],
      'date_fin' => $datesConfigCalendar['date_fin'],
    ]);

    // Traiter le formulaire (si nécessaire)
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // Logique de traitement du formulaire si nécessaire
      $data = $form->getData();
      $this->configDatesService->setDates($data['date_debut'],$data['date_fin']);
      // Par exemple, utilisez les dates pour filtrer les événements ou autre
    }

    // Récupérer tous les événements
    $events = $repository->findAll();

    // Passer le formulaire et les événements à la vue
    return $this->render('google_calendar/index.html.twig', [
      'events' => $events,
      'form' => $form->createView(),
      'controller_name' => 'GoogleCalendarController',
    ]);
  }

  #[Route('/google-calendar/fetch', name: 'app_google_calendar_fetch')]
  public function fetchEvents(GoogleCalendarService $googleCalendarService): Response
  {
    // Appel à la fonction pour récupérer et stocker les événements
    $googleCalendarService->fetchAndStoreEvents();

    // Redirige vers la page de la liste des événements après l'import
    return $this->redirectToRoute('app_google_calendar');
  }

  #[Route('/google-calendar/delete-all', name: 'app_google_calendar_delete_all', methods: ['POST'])]
  public function deleteAllEvents(Request $request, GoogleCalendarEventRepository $repository, EntityManagerInterface $entityManager): Response
  {
    if ($this->isCsrfTokenValid('delete_all_events', $request->request->get('_token'))) {

      // Récupère tous les événements et les supprime
      $events = $repository->findAll();
      foreach ($events as $event) {
        $entityManager->remove($event);
      }
      $entityManager->flush();
    }
    // Redirige vers la page de la liste des événements après la suppression
    return $this->redirectToRoute('app_google_calendar');
  }

  #[Route('/config/dates', name: 'config_dates')]
  public function configureDates(Request $request): Response
  {
    // Charge les dates de configuration depuis le fichier YAML
    $dates = $this->configDatesService->getDates();

    // Crée le formulaire avec les valeurs de date actuelles
    $form = $this->createForm(ConfigDatesType::class, [
      'date_debut' => $dates['date_debut'],
      'date_fin' => $dates['date_fin'],
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
      $data = $form->getData();

      // Met à jour les dates dans le fichier de configuration
      $this->configDatesService->setDates($data['date_debut'], $data['date_fin']);

      $this->addFlash('success', 'Les dates de configuration ont été mises à jour.');

      // Redirige pour afficher les changements
      return $this->redirectToRoute('config_dates');
    }

    return $this->render('config_dates/configure.html.twig', [
      'form' => $form->createView(),
    ]);
  }

}