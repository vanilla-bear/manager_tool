<?php

namespace App\Entity;

use App\Enum\TypePeriodEnum;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\TimePeriodRepository;

#[ORM\Entity(repositoryClass: TimePeriodRepository::class)]
class TimePeriod {

  #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
  private $id;

  #[ORM\Column(type: 'string', length: 100)]
  private $name;

  #[ORM\Column(type: 'date')]
  private $startDate;

  #[ORM\Column(type: 'date')]
  private $endDate;

  #[ORM\Column(type: 'integer')]
  private $workingDays; // Champ pour stocker le nombre de jours travaillés

  #[ORM\Column(type: 'string', length: 20, enumType: TypePeriodEnum::class)]
  private TypePeriodEnum $type;

  // Nouveau champ pour la vélocité estimée
  #[ORM\Column(type: 'float', nullable: true)]
  private ?float $estimatedVelocity = null;

  // Nouveau champ pour la vélocité communiqué
  #[ORM\Column(type: 'float', nullable: true)]
  private ?float $communicatedVelocity = null;

  // Nouveau champ pour la vélocité finale
  #[ORM\Column(type: 'float', nullable: true)]
  private ?float $finalVelocity = null;

  // Nouveau champ pour le nombre de points ajoutés dans le sprint
  #[ORM\Column(type: 'integer', nullable: true)]
  private ?int $pointsAdded = null;

  #[ORM\Column(type: 'json', nullable: true)]
  private ?array $capacityData = null;

  public function getId(): ?int {
    return $this->id;
  }

  public function getName(): ?string {
    return $this->name;
  }

  public function setName(string $name): self {
    $this->name = $name;
    return $this;
  }

  public function getStartDate(): ?\DateTimeInterface {
    return $this->startDate;
  }

  public function setStartDate(\DateTimeInterface $startDate): self {
    $this->startDate = $startDate;
    return $this;
  }

  public function getEndDate(): ?\DateTimeInterface {
    return $this->endDate;
  }

  public function setEndDate(\DateTimeInterface $endDate): self {
    $this->endDate = $endDate;
//    dd($this);
    return $this;
  }

  public function getWorkingDays(): ?int {
    return $this->workingDays;
  }

  public function setWorkingDays(int $workingDays): self {
    $this->workingDays = $workingDays;
    return $this;
  }

  public function getType(): ?TypePeriodEnum {
    return $this->type;
  }

  public function setType(TypePeriodEnum $type): self {
    $this->type = $type;
    return $this;
  }

  // Getter et Setter pour vélocité estimée
  public function getEstimatedVelocity(): ?float
  {
    return $this->estimatedVelocity;
  }

  public function setEstimatedVelocity(?float $estimatedVelocity): self
  {
    $this->estimatedVelocity = $estimatedVelocity;
    return $this;
  }

  // Getter et Setter pour vélocité finale
  public function getFinalVelocity(): ?float
  {
    return $this->finalVelocity;
  }

  public function setFinalVelocity(?float $finalVelocity): self
  {
    $this->finalVelocity = $finalVelocity;
    return $this;
  }

  // Getter et Setter pour vélocité communiqué
  public function getCommunicatedVelocity(): ?float
  {
    return $this->finalVelocity;
  }

  public function setCommunicatedVelocity(?float $communicatedVelocity): self
  {
    $this->communicatedVelocity = $communicatedVelocity;
    return $this;
  }

  // Getter et Setter pour nombre de points ajoutés dans le sprint
  public function getPointsAdded(): ?int
  {
    return $this->pointsAdded;
  }

  public function setPointsAdded(?int $pointsAdded): self
  {
    $this->pointsAdded = $pointsAdded;
    return $this;
  }

  // Getter et Setter pour capacityData
  public function getCapacityData(): ?array
  {
    return $this->capacityData;
  }

  public function setCapacityData(?array $capacityData): self
  {
    $this->capacityData = $capacityData;
    return $this;
  }

}
