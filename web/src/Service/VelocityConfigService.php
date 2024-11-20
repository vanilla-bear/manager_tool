<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class VelocityConfigService
{
  private string $configFilePath;

  public function __construct()
  {
    // Chemin vers le fichier de configuration YAML
    $this->configFilePath = __DIR__ . '/../../config/packages/velocity.yaml';
  }

  public function getVelocity(): float
  {
    // Charger la configuration YAML
    $config = Yaml::parseFile($this->configFilePath);

    // Retourne la valeur de vélocité, ou une valeur par défaut si non définie
    return $config['parameters']['team_velocity'] ?? 1.0;
  }

  public function setVelocity(float $velocity): void
  {
    // Charger la configuration YAML actuelle
    $config = Yaml::parseFile($this->configFilePath);

    // Mettre à jour la vélocité
    $config['parameters']['team_velocity'] = $velocity;

    // Enregistrer la configuration mise à jour dans le fichier YAML
    file_put_contents($this->configFilePath, Yaml::dump($config));
  }
}
