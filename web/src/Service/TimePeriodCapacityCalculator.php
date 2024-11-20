<?php

namespace App\Service;

use App\Entity\TimePeriod;
use App\Entity\TeamMember;
use App\Entity\GoogleCalendarEvent;

use Doctrine\ORM\EntityManagerInterface;

class TimePeriodCapacityCalculator
{
  private EntityManagerInterface $entityManager;
  private DatePeriodService $datePeriodService;

  public function __construct(
    EntityManagerInterface $entityManager,
    DatePeriodService $datePeriodService,
  )
  {
    $this->entityManager = $entityManager;
    $this->datePeriodService = $datePeriodService;
  }

  public function calculateCapacity(TimePeriod $timePeriod ): array
  {
    $totalCapacity = 0;
    $memberCapacities = [];
    $startPeriod = $timePeriod->getStartDate();
    $endPeriod = $timePeriod->getEndDate();
    $endPeriod->setTime(23, 59, 59);

    $workingDays = $this->datePeriodService->calculateWorkingDays($startPeriod, $endPeriod);
    // Récupérer tous les jours fériés pour la période
    $holidays = $this->datePeriodService->getAllHolidays($startPeriod, $endPeriod);

    dump($workingDays,$holidays);
    // Récupérer tous les membres de l'équipe indépendamment de la période
    $teamMembers = $this->entityManager->getRepository(TeamMember::class)->findAll();
//    $teamMembers[] = $this->entityManager->getRepository(TeamMember::class)->find(5);

    foreach ($teamMembers as $member) {
      // Calculer la capacité de base pour ce membre
      $memberCapacity = $workingDays * $member->getWorkingHoursPerDay();

      // Récupérer les événements Google Calendar qui contiennent le nom du membre dans le titre
      $events = $this->entityManager->getRepository(GoogleCalendarEvent::class)
        ->createQueryBuilder('e')
        ->where('e.title LIKE :name')
        ->setParameter('name', '%' . $member->getName() . '%')
        ->andWhere('e.startTime <= :periodEnd')
        ->andWhere('e.endTime >= :periodStart')
        ->setParameter('periodStart', $startPeriod)
        ->setParameter('periodEnd', $endPeriod)
        ->getQuery()
        ->getResult();

      $workedDays = 0;
      $leaveDays = 0;

      // Calculer les jours de travail et de congé sur la période
      foreach ($this->datePeriodService->createDatePeriod($startPeriod, $endPeriod) as $date) {
        // Vérifier si le jour est un week-end
        if (in_array($date->format('N'), [6, 7])) {
          continue; // Ignorer les week-ends
        }

        if (in_array($date->format('Y-m-d'), $holidays)) {
          $leaveDays++;
          continue;
        }
        $isOnLeave = false;
        $hoursAbsent = 0;
        $halfDayThreshold = $member->getWorkingHoursPerDay() / 2;
        /** @var GoogleCalendarEvent $event */
        foreach ($events as $event) {
          // Clonez `$date` pour préserver l'originale
          $dayStart = (clone $date)->setTime(0, 0);
          $dayEnd = (clone $date)->setTime(23, 59);
          if ($event->getStartTime() <= $dayEnd && $event->getEndTime() >= $dayStart) {
            $isOnLeave = true;

            // Calculer les heures d'absence pour l'événement en utilisant les clones
            $eventStartTime = max($event->getStartTime(), $dayStart);
            $eventEndTime = min($event->getEndTime(), $dayEnd);

            $hoursAbsent = ($eventEndTime->getTimestamp() - $eventStartTime->getTimestamp()) / 3600;

            break;
          }
        }
        if ($isOnLeave) {
          if($hoursAbsent <= 2 ){
            $workedDays++;
          } elseif ($hoursAbsent > 2 && $hoursAbsent < 6 ){
            $workedDays += 0.5;
            $leaveDays += 0.5;
          } else {
            $leaveDays++;

          }
        } else {
          $workedDays++;
        }
      }

      // Calculer la capacité ajustée en fonction des jours travaillés
      $adjustedCapacity = $workedDays * $member->getWorkingHoursPerDay();

      // Ajouter les informations pour ce membre
      $memberCapacities[$member->getName()] = [
        'base_capacity' => $memberCapacity,
        'adjusted_capacity' => $adjustedCapacity,
        'worked_days' => $workedDays,
        'leave_days' => $leaveDays,
        'events' => $events, // Liste des événements pour référence
      ];

      // Ajouter la capacité ajustée au total de l'équipe
      $totalCapacity += $adjustedCapacity;
    }

    return [
      'total_capacity' => $totalCapacity,
      'member_capacities' => $memberCapacities
    ];
  }

  /**
   * Calcule le pourcentage de présence de l'équipe et la vélocité ajustée
   *
   * @param array $data Les données de capacité
   * @param float $fullVelocity La vélocité maximale de l'équipe (100 % de présence)
   * @param int $sprintDuration La durée du sprint en jours
   * @return array ['presence_percentage' => float, 'adjusted_velocity' => float]
   */
  public function calculateVelocity(array $data, float $fullVelocity, int $sprintDuration): array
  {
    // Initialisation des variables
    $totalDaysPresent = 0;
    $totalDaysTheoretical = count($data['member_capacities']) * $sprintDuration;

    // Calcul du nombre total de jours de présence pour chaque membre
    foreach ($data['member_capacities'] as $member => $capacities) {
      $totalDaysPresent += $capacities['worked_days'];
    }

    // Calcul du pourcentage de présence de l'équipe
    $presencePercentage = ($totalDaysPresent / $totalDaysTheoretical) * 100;

    // Calcul de la vélocité ajustée
    $adjustedVelocity = ($fullVelocity * $presencePercentage) / 100;

    // Résultats
    return [
      'presence_percentage' => $presencePercentage,
      'adjusted_velocity' => $adjustedVelocity
    ];
  }

}
