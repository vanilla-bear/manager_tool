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
    $client->setAuthConfig(__DIR__ . '/../../config/google/credentials2.json');
    $client->addScope(Calendar::CALENDAR_READONLY);

    $calendarService = new Calendar($client);
//    $calendarId = 'romain.dalverny@studi.fr'; // Or your custom calendar ID

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

//    $events = $calendarService->calendarList->listCalendarList();

    $events = $calendarService->events->listEvents($calendarId, $params);

//    dump($events,$events->getItems());
//    dd($events2,$events2->getItems());

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
