<?php

namespace App\Service;

use DateTime;
use DatePeriod;
use DateInterval;

class DatePeriodService {

  /**
   * Crée un DatePeriod pour la période définie entre deux dates, incluant le jour de fin.
   */
  public function createDatePeriod(DateTime $startDate, DateTime $endDate): DatePeriod {
    $interval = new DateInterval('P1D');
    $end = (clone $endDate)->modify('+1 day'); // Inclut le dernier jour

    return new DatePeriod($startDate, $interval, $endDate);
  }

  /**
   * Calcule le nombre de jours ouvrés dans une période, en excluant les week-ends.
   */
  public function calculateWorkingDays(DateTime $startDate, DateTime $endDate, bool $addHolidays = true): int {

    $holidays = [];
    if ($addHolidays) {
      $currentYear = (int) $startDate->format('Y');
      $nextYear = $currentYear + 1;

      $holidays = array_merge(
        $this->getFrenchHolidays($currentYear),
        $this->getFrenchHolidays($nextYear),
        $this->getStudiHolidays()
      );
    }

    $startDateCloned = clone $startDate; // Clone de la date pour ne pas affecter l'original
    $workingDays = 0;

    while ($startDateCloned <= $endDate) {
      // Si le jour est un jour ouvrable (lundi-vendredi) et n'est pas un jour férié
      if ($startDateCloned->format('N') < 6 && !in_array($startDateCloned->format('Y-m-d'), $holidays)) {
        $workingDays++;
      }
      $startDateCloned->modify('+1 day');
    }

    return $workingDays;
  }

  private function getFrenchHolidays(int $year): array
  {
    // Liste des jours fériés fixes en France
    $fixedHolidays = [
      "$year-01-01", // Jour de l'An
      "$year-05-01", // Fête du Travail
      "$year-05-08", // Victoire 1945
      "$year-07-14", // Fête Nationale
      "$year-08-15", // Assomption
      "$year-11-01", // Toussaint
      "$year-11-11", // Armistice
      "$year-12-25"  // Noël
    ];

    // Calcul des jours fériés variables (Pâques, Ascension, Pentecôte)
    $easter = easter_date($year);
    $easterDate = (new DateTime())->setTimestamp($easter);
    $variableHolidays = [
      $easterDate->format('Y-m-d'),                                     // Pâques
      $easterDate->modify('+1 day')->format('Y-m-d'),                   // Lundi de Pâques
      $easterDate->modify('+38 days')->format('Y-m-d'),                 // Ascension
      $easterDate->modify('+10 days')->format('Y-m-d')                  // Lundi de Pentecôte
    ];

    return array_merge($fixedHolidays, $variableHolidays);
  }

  private function getStudiHolidays(): array
  {
    return [
      "2024-12-26",
      "2025-05-02",
      "2025-05-09",
      "2025-05-30",
      "2025-06-09",
    ];
  }

  public function getAllHolidays(DateTime $startDate, DateTime $endDate): array
  {
    // Collecte les années nécessaires pour couvrir toute la période
    $years = range((int) $startDate->format('Y'), (int) $endDate->format('Y'));

    $allHolidays = [];
    foreach ($years as $year) {
      $allHolidays = array_merge($allHolidays, $this->getFrenchHolidays($year));
    }

    // Ajoute les jours fériés spécifiques à Studi
    return array_merge($allHolidays, $this->getStudiHolidays());
  }


}
