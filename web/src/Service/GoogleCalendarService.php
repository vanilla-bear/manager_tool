<?php

namespace App\Service;

use AllowDynamicProperties;
use App\Entity\GoogleCalendarEvent;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client;
use Google\Service\Calendar;
use Symfony\Component\Yaml\Yaml;

#[AllowDynamicProperties] class GoogleCalendarService
{
  private EntityManagerInterface $entityManager;

  public function __construct(EntityManagerInterface $entityManager)
  {
    $this->entityManager = $entityManager;
    $this->configFilePath = __DIR__ . '/../../config/packages/google_calendar.yaml';
  }

  public function fetchAndStoreEvents()
  {
    $client = new Client();
    
    // Use service account credentials for server-to-server authentication
    $client->setAuthConfig(__DIR__ . '/../../config/google/service-account-key.json');
    $client->setScopes([Calendar::CALENDAR_READONLY]);

    $calendarService = new Calendar($client);

    $calendarId = 'c_6f246edceaaab673424c19d81d4c87fc41a298894fbc445562a8ea431a560068@group.calendar.google.com';

    // Récupérer les dates de configuration
    $dates = $this->getDates();
    $startDate = $dates['date_debut'];
    $endDate = $dates['date_fin'];
    $endDate->setTime(23, 59, 59);

    $params = [
      'timeMin' => $startDate->format(\DateTime::RFC3339),
      'timeMax' => $endDate->format(\DateTime::RFC3339),
      'singleEvents' => true,
      'orderBy' => 'startTime'
    ];

    try {
      $events = $calendarService->events->listEvents($calendarId, $params);

      foreach ($events->getItems() as $event) {
        $calendarEvent = new GoogleCalendarEvent();
        $calendarEvent->setEventId($event->getId());
        $calendarEvent->setTitle($event->getSummary());
        $calendarEvent->setStartTime(new \DateTime($event->getStart()->getDateTime() ?: $event->getStart()->getDate()));
        $calendarEvent->setEndTime(new \DateTime($event->getEnd()->getDateTime() ?: $event->getEnd()->getDate()));
        $calendarEvent->setDescription($event->getDescription());

        $this->entityManager->persist($calendarEvent);
      }

      $this->entityManager->flush();
    } catch (\Exception $e) {
      // Log the error for debugging
      error_log('Google Calendar API Error: ' . $e->getMessage());
      throw $e;
    }
  }

  /**
   * Synchronise intelligemment les événements Google Calendar
   * - Met à jour les événements existants
   * - Ajoute les nouveaux événements
   * - Supprime les événements qui n'existent plus
   */
  public function syncEvents()
  {
    $client = new Client();
    
    // Use service account credentials for server-to-server authentication
    $client->setAuthConfig(__DIR__ . '/../../config/google/service-account-key.json');
    $client->setScopes([Calendar::CALENDAR_READONLY]);

    $calendarService = new Calendar($client);

    $calendarId = 'c_6f246edceaaab673424c19d81d4c87fc41a298894fbc445562a8ea431a560068@group.calendar.google.com';

    // Récupérer les dates de configuration
    $dates = $this->getDates();
    $startDate = $dates['date_debut'];
    $endDate = $dates['date_fin'];
    $endDate->setTime(23, 59, 59);

    $params = [
      'timeMin' => $startDate->format(\DateTime::RFC3339),
      'timeMax' => $endDate->format(\DateTime::RFC3339),
      'singleEvents' => true,
      'orderBy' => 'startTime'
    ];

    try {
      $events = $calendarService->events->listEvents($calendarId, $params);
      
      // Récupérer tous les événements existants dans la base de données
      $existingEvents = $this->entityManager->getRepository(GoogleCalendarEvent::class)->findAll();
      $existingEventsMap = [];
      foreach ($existingEvents as $existingEvent) {
        $existingEventsMap[$existingEvent->getEventId()] = $existingEvent;
      }

      $googleEventIds = [];
      $stats = [
        'added' => 0,
        'updated' => 0,
        'deleted' => 0
      ];

      // Traiter chaque événement de Google Calendar
      foreach ($events->getItems() as $event) {
        $googleEventIds[] = $event->getId();
        
        if (isset($existingEventsMap[$event->getId()])) {
          // L'événement existe déjà, vérifier s'il a changé
          $existingEvent = $existingEventsMap[$event->getId()];
          $hasChanged = false;
          
          // Comparer les propriétés
          if ($existingEvent->getTitle() !== $event->getSummary()) {
            $existingEvent->setTitle($event->getSummary());
            $hasChanged = true;
          }
          
          $newStartTime = new \DateTime($event->getStart()->getDateTime() ?: $event->getStart()->getDate());
          if ($existingEvent->getStartTime() != $newStartTime) {
            $existingEvent->setStartTime($newStartTime);
            $hasChanged = true;
          }
          
          $newEndTime = new \DateTime($event->getEnd()->getDateTime() ?: $event->getEnd()->getDate());
          if ($existingEvent->getEndTime() != $newEndTime) {
            $existingEvent->setEndTime($newEndTime);
            $hasChanged = true;
          }
          
          if ($existingEvent->getDescription() !== $event->getDescription()) {
            $existingEvent->setDescription($event->getDescription());
            $hasChanged = true;
          }
          
          if ($hasChanged) {
            $stats['updated']++;
          }
          
          // Retirer de la map pour éviter la suppression
          unset($existingEventsMap[$event->getId()]);
        } else {
          // Nouvel événement
          $calendarEvent = new GoogleCalendarEvent();
          $calendarEvent->setEventId($event->getId());
          $calendarEvent->setTitle($event->getSummary());
          $calendarEvent->setStartTime(new \DateTime($event->getStart()->getDateTime() ?: $event->getStart()->getDate()));
          $calendarEvent->setEndTime(new \DateTime($event->getEnd()->getDateTime() ?: $event->getEnd()->getDate()));
          $calendarEvent->setDescription($event->getDescription());

          $this->entityManager->persist($calendarEvent);
          $stats['added']++;
        }
      }

      // Supprimer les événements qui n'existent plus dans Google Calendar
      foreach ($existingEventsMap as $eventToDelete) {
        $this->entityManager->remove($eventToDelete);
        $stats['deleted']++;
      }

      $this->entityManager->flush();
      
      return $stats;
    } catch (\Exception $e) {
      // Log the error for debugging
      error_log('Google Calendar API Error: ' . $e->getMessage());
      throw $e;
    }
  }

  public function getDates(): array
  {
    // Charge les données de configuration YAML
    $config = Yaml::parseFile($this->configFilePath);

    return [
      'date_debut' => new \DateTime($config['parameters']['config_dates']['date_debut']),
      'date_fin' => new \DateTime($config['parameters']['config_dates']['date_fin']),
    ];
  }

  public function setDates(\DateTime $dateDebut, \DateTime $dateFin): void
  {
    // Charge le contenu actuel du fichier YAML
    $config = Yaml::parseFile($this->configFilePath);

    // Met à jour les valeurs de date
    $config['parameters']['config_dates']['date_debut'] = $dateDebut->format('Y-m-d');
    $config['parameters']['config_dates']['date_fin'] = $dateFin->format('Y-m-d');

    // Écrit les nouvelles valeurs dans le fichier YAML
    file_put_contents($this->configFilePath, Yaml::dump($config));
  }
}
