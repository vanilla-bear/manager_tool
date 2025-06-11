<?php

namespace App\Entity;

use App\Repository\MonthlyStatsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonthlyStatsRepository::class)]
class MonthlyStats
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $month = null;

    #[ORM\Column]
    private ?int $bugsCount = 0;

    #[ORM\Column]
    private ?int $deliveredTicketsCount = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $syncedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMonth(): ?\DateTimeImmutable
    {
        return $this->month;
    }

    public function setMonth(\DateTimeImmutable $month): static
    {
        $this->month = $month;
        return $this;
    }

    public function getBugsCount(): ?int
    {
        return $this->bugsCount;
    }

    public function setBugsCount(int $bugsCount): static
    {
        $this->bugsCount = $bugsCount;
        return $this;
    }

    public function getDeliveredTicketsCount(): ?int
    {
        return $this->deliveredTicketsCount;
    }

    public function setDeliveredTicketsCount(int $deliveredTicketsCount): static
    {
        $this->deliveredTicketsCount = $deliveredTicketsCount;
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

    public function getBugRate(): ?float
    {
        if ($this->deliveredTicketsCount <= 0) {
            return null;
        }
        return $this->bugsCount / $this->deliveredTicketsCount;
    }
} 