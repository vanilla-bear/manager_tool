<?php

namespace App\Entity;

use App\Repository\SprintRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SprintRepository::class)]
class Sprint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $jiraId = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $completedPoints = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $committedPoints = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $devsTerminesPoints = 0.0;

    #[ORM\Column]
    private int $devsTerminesCount = 0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $addedPointsDuringSprint = 0.0;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $plannedCapacityDays = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $capacityDays = 0.0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $syncedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJiraId(): ?int
    {
        return $this->jiraId;
    }

    public function setJiraId(int $jiraId): static
    {
        $this->jiraId = $jiraId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getCompletedPoints(): float
    {
        return $this->completedPoints;
    }

    public function setCompletedPoints(float $completedPoints): static
    {
        $this->completedPoints = $completedPoints;

        return $this;
    }

    public function getCommittedPoints(): float
    {
        return $this->committedPoints;
    }

    public function setCommittedPoints(float $committedPoints): static
    {
        $this->committedPoints = $committedPoints;

        return $this;
    }

    public function getDevsTerminesPoints(): float
    {
        return $this->devsTerminesPoints;
    }

    public function setDevsTerminesPoints(float $devsTerminesPoints): static
    {
        $this->devsTerminesPoints = $devsTerminesPoints;

        return $this;
    }

    public function getDevsTerminesCount(): int
    {
        return $this->devsTerminesCount;
    }

    public function setDevsTerminesCount(int $devsTerminesCount): static
    {
        $this->devsTerminesCount = $devsTerminesCount;

        return $this;
    }

    public function getAddedPointsDuringSprint(): float
    {
        return $this->addedPointsDuringSprint;
    }

    public function setAddedPointsDuringSprint(float $addedPointsDuringSprint): static
    {
        $this->addedPointsDuringSprint = $addedPointsDuringSprint;

        return $this;
    }

    public function getPlannedCapacityDays(): ?float
    {
        return $this->plannedCapacityDays;
    }

    public function setPlannedCapacityDays(?float $plannedCapacityDays): static
    {
        $this->plannedCapacityDays = $plannedCapacityDays;

        return $this;
    }

    public function getCapacityDays(): float
    {
        return $this->capacityDays;
    }

    public function setCapacityDays(float $capacityDays): static
    {
        $this->capacityDays = $capacityDays;

        return $this;
    }

    public function getSyncedAt(): ?DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    public function getVelocityPerDay(): ?float
    {
        if ($this->capacityDays <= 0) {
            return null;
        }
        return $this->completedPoints / $this->capacityDays;
    }

    /**
     * Calcule le taux de completion par rapport au scope total (initial + ajouts)
     * FORMULE: (Completed Points / (Committed Points + Added Points)) × 100
     */
    public function getCompletionRate(): ?float
    {
        $totalScope = $this->committedPoints + $this->addedPointsDuringSprint;
        if ($totalScope <= 0) {
            return null;
        }
        return min(($this->completedPoints / $totalScope) * 100, 100);
    }

    /**
     * Calcule le taux de completion des devs par rapport au scope total (initial + ajouts)
     * FORMULE: (Devs Terminés Points / (Committed Points + Added Points)) × 100
     */
    public function getDevCompletionRate(): ?float
    {
        $totalScope = $this->committedPoints + $this->addedPointsDuringSprint;
        if ($totalScope <= 0) {
            return null;
        }
        return ($this->devsTerminesPoints / $totalScope) * 100;
    }

    /**
     * Ancienne méthode - taux de completion par rapport au scope initial seulement
     * @deprecated Utiliser getCompletionRate() à la place
     */
    public function getInitialCompletionRate(): ?float
    {
        if ($this->committedPoints <= 0) {
            return null;
        }
        return min(($this->completedPoints / $this->committedPoints) * 100, 100);
    }

    /**
     * Ancienne méthode - taux de completion des devs par rapport au scope initial seulement
     * @deprecated Utiliser getDevCompletionRate() à la place
     */
    public function getInitialDevCompletionRate(): ?float
    {
        if ($this->committedPoints <= 0) {
            return null;
        }
        return ($this->devsTerminesPoints / $this->committedPoints) * 100;
    }

    public function getAdjustedCompletionRate(): ?float
    {
        if ($this->committedPoints <= 0) {
            return null;
        }
        $totalToComplete = $this->committedPoints + ($this->addedPointsDuringSprint ?? 0);
        return min(($this->completedPoints / $totalToComplete) * 100, 100);
    }

    /**
     * Retourne le pourcentage de points ajoutés par rapport aux points initialement engagés
     */
    public function getAddedPointsPercentage(): ?float
    {
        if ($this->committedPoints <= 0) {
            return null;
        }
        return ($this->addedPointsDuringSprint / $this->committedPoints) * 100;
    }
} 