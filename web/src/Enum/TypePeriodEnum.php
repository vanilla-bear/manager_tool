<?php

namespace App\Enum;

enum TypePeriodEnum: string
{
  case SPRINT = 'sprint';
  case LIBRE = 'libre';

  public static function getChoices(): array
  {
    return [
      'Sprint' => self::SPRINT->value,
      'Libre' => self::LIBRE->value,
    ];
  }

}
