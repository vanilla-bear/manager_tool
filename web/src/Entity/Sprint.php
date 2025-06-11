<?php

namespace App\Entity;

use App\Repository\SprintRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SprintRepository::class)]
class Sprint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $jiraId = null;

    #[ORM\Column]
    private ?int $completedPoints = null;

    #[ORM\Column]
    private ?int $devsTerminesPoints = null;

    #[ORM\Column]
    private ?int $committedPoints = null;

    #[ORM\Column]
    private ?float $capacityDays = null;

    #[ORM\Column]
    private ?float $plannedCapacityDays = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $syncedAt = null;

    #[ORM\Column]
    private ?int $devsTerminesCount = 0;

    #[ORM\Column(nullable: true)]
    private ?int $addedPointsDuringSprint = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getJiraId(): ?int
    {
        return $this->jiraId;
    }

    public function setJiraId(int $jiraId): static
    {
        $this->jiraId = $jiraId;
        return $this;
    }

    public function getCompletedPoints(): ?int
    {
        return $this->completedPoints;
    }

    public function setCompletedPoints(int $completedPoints): static
    {
        $this->completedPoints = $completedPoints;
        return $this;
    }

    public function getDevsTerminesPoints(): ?int
    {
        return $this->devsTerminesPoints;
    }

    public function setDevsTerminesPoints(int $points): static
    {
        $this->devsTerminesPoints = $points;
        return $this;
    }

    public function getCommittedPoints(): ?int
    {
        return $this->committedPoints;
    }

    public function setCommittedPoints(int $committedPoints): static
    {
        $this->committedPoints = $committedPoints;
        return $this;
    }

    public function getCapacityDays(): ?float
    {
        return $this->capacityDays;
    }

    public function setCapacityDays(float $capacityDays): static
    {
        $this->capacityDays = $capacityDays;
        return $this;
    }

    public function getPlannedCapacityDays(): ?float
    {
        return $this->plannedCapacityDays;
    }

    public function setPlannedCapacityDays(float $plannedCapacityDays): static
    {
        $this->plannedCapacityDays = $plannedCapacityDays;
        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getSyncedAt(): ?\DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeImmutable $syncedAt): static
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

    public function getCompletionRate(): ?float
    {
        if ($this->committedPoints <= 0) {
            return null;
        }
        return min(($this->completedPoints / $this->committedPoints) * 100, 100);
    }

    public function getDevCompletionRate(): ?float
    {
        if ($this->committedPoints <= 0) {
            return null;
        }
        return ($this->devsTerminesPoints / $this->committedPoints) * 100;
    }

    public function getDevsTerminesCount(): ?int
    {
        return $this->devsTerminesCount;
    }

    public function setDevsTerminesCount(int $count): static
    {
        $this->devsTerminesCount = $count;
        return $this;
    }

    public function getAddedPointsDuringSprint(): ?int
    {
        return $this->addedPointsDuringSprint;
    }

    public function setAddedPointsDuringSprint(?int $points): static
    {
        $this->addedPointsDuringSprint = $points;
        return $this;
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