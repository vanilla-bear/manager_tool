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
   * CALCUL DU POURCENTAGE DE PRÉSENCE :
   * Pourcentage = (Jours présents totaux / Jours théoriques totaux) × 100
   * 
   * Où :
   * - Jours présents totaux = Somme des jours travaillés de chaque membre
   * - Jours théoriques totaux = Nombre de membres × Durée du sprint
   * 
   * EXEMPLE :
   * - Équipe de 5 personnes sur un sprint de 10 jours
   * - Jours théoriques = 5 × 10 = 50 jours
   * - Si les membres ont travaillé : 8 + 9 + 7 + 10 + 8 = 42 jours
   * - Pourcentage de présence = (42/50) × 100 = 84%
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

  /**
   * Calcule la vélocité estimée pour une période donnée
   * 
   * FORMULE DE CALCUL :
   * Vélocité estimée = Vélocité globale × (Pourcentage de présence de l'équipe / 100)
   * 
   * DÉTAIL DU CALCUL :
   * 1. On calcule la capacité de chaque membre (jours travaillés × heures/jour)
   * 2. On déduit les absences (congés, événements Google Calendar)
   * 3. On calcule le pourcentage de présence = (jours présents totaux / jours théoriques totaux) × 100
   * 4. On applique ce pourcentage à la vélocité globale configurée
   * 
   * EXEMPLE :
   * - Vélocité globale : 39 points/sprint (100% de présence)
   * - Équipe de 5 personnes sur 10 jours = 50 jours théoriques
   * - 3 personnes absentes 2 jours = 6 jours d'absence
   * - Jours présents : 44 jours
   * - Pourcentage de présence : (44/50) × 100 = 88%
   * - Vélocité estimée : 39 × 0.88 = 34.3 points/sprint
   *
   * @param TimePeriod $timePeriod La période pour laquelle calculer la vélocité
   * @param float $globalVelocity La vélocité globale de l'équipe (100% de présence)
   * @return array ['capacity_data' => array, 'velocity_data' => array, 'estimated_velocity' => float]
   */
  public function calculateEstimatedVelocity(TimePeriod $timePeriod, float $globalVelocity): array
  {
    // Calculer la capacité de l'équipe pour la période
    $capacityData = $this->calculateCapacity($timePeriod);
    
    // Calculer la durée du sprint en jours ouvrés
    $sprintDuration = $this->datePeriodService->calculateWorkingDays(
      $timePeriod->getStartDate(), 
      $timePeriod->getEndDate(), 
      false
    );
    
    // Calculer la vélocité ajustée
    $velocityData = $this->calculateVelocity($capacityData, $globalVelocity, $sprintDuration);
    
    return [
      'capacity_data' => $capacityData,
      'velocity_data' => $velocityData,
      'estimated_velocity' => $velocityData['adjusted_velocity']
    ];
  }

  /**
   * Calcule et met à jour directement une entité TimePeriod avec la vélocité estimée
   * Cette méthode est pratique pour les cas où on veut calculer et sauvegarder en une seule opération
   *
   * @param TimePeriod $timePeriod La période à mettre à jour
   * @param float $globalVelocity La vélocité globale de l'équipe
   * @return array Les résultats du calcul pour information
   */
  public function calculateAndUpdateTimePeriod(TimePeriod $timePeriod, float $globalVelocity): array
  {
    $result = $this->calculateEstimatedVelocity($timePeriod, $globalVelocity);
    
    // Mettre à jour l'entité avec les résultats
    $timePeriod->setCapacityData($result['capacity_data']);
    $timePeriod->setEstimatedVelocity($result['estimated_velocity']);
    
    return $result;
  }

  /**
   * Calcule la vélocité estimée pour plusieurs périodes
   * Cette méthode est utile pour les traitements par lot
   *
   * @param array $timePeriods Tableau d'entités TimePeriod
   * @param float $globalVelocity La vélocité globale de l'équipe
   * @return array ['periods' => array, 'summary' => array]
   */
  public function calculateEstimatedVelocityForMultiplePeriods(array $timePeriods, float $globalVelocity): array
  {
    $results = [];
    $totalEstimatedVelocity = 0;
    $periodCount = 0;

    foreach ($timePeriods as $timePeriod) {
      $result = $this->calculateEstimatedVelocity($timePeriod, $globalVelocity);
      
      // Mettre à jour l'entité
      $timePeriod->setCapacityData($result['capacity_data']);
      $timePeriod->setEstimatedVelocity($result['estimated_velocity']);
      
      $results[] = [
        'timePeriod' => $timePeriod,
        'result' => $result
      ];
      
      $totalEstimatedVelocity += $result['estimated_velocity'];
      $periodCount++;
    }

    return [
      'periods' => $results,
      'summary' => [
        'total_periods' => $periodCount,
        'average_estimated_velocity' => $periodCount > 0 ? $totalEstimatedVelocity / $periodCount : 0,
        'total_estimated_velocity' => $totalEstimatedVelocity
      ]
    ];
  }

}
