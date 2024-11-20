<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

class EndDateTimeTransformer implements DataTransformerInterface {

  public function transform($date): mixed {
    return $date;
  }

  public function reverseTransform($date): mixed {
    if ($date instanceof \DateTime) {
      // Définit l'heure à 23:59:59 pour la date de fin uniquement
      $date->setTime(23, 59, 59);
    }
    return $date;
  }

}
