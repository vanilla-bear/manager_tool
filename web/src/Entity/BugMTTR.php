<?php

namespace App\Entity;

use App\Repository\BugMTTRRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BugMTTRRepository::class)]
class BugMTTR
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $bugKey = null;

    #[ORM\Column(length: 255)]
    private ?string $summary = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $aFaireAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $devsTerminesAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $termineAt = null;

    #[ORM\Column(length: 50)]
    private ?string $currentStatus = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $syncedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBugKey(): ?string
    {
        return $this->bugKey;
    }

    public function setBugKey(string $bugKey): static
    {
        $this->bugKey = $bugKey;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getAFaireAt(): ?\DateTimeImmutable
    {
        return $this->aFaireAt;
    }

    public function setAFaireAt(?\DateTimeImmutable $aFaireAt): static
    {
        $this->aFaireAt = $aFaireAt;
        return $this;
    }

    public function getDevsTerminesAt(): ?\DateTimeImmutable
    {
        return $this->devsTerminesAt;
    }

    public function setDevsTerminesAt(?\DateTimeImmutable $devsTerminesAt): static
    {
        $this->devsTerminesAt = $devsTerminesAt;
        return $this;
    }

    public function getTermineAt(): ?\DateTimeImmutable
    {
        return $this->termineAt;
    }

    public function setTermineAt(?\DateTimeImmutable $termineAt): static
    {
        $this->termineAt = $termineAt;
        return $this;
    }

    public function getCurrentStatus(): ?string
    {
        return $this->currentStatus;
    }

    public function setCurrentStatus(string $currentStatus): static
    {
        $this->currentStatus = $currentStatus;
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

    public function getCreatedToTermineTime(): ?int
    {
        if (!$this->termineAt || !$this->createdAt) {
            return null;
        }
        return $this->termineAt->getTimestamp() - $this->createdAt->getTimestamp();
    }

    public function getAFaireToTermineTime(): ?int
    {
        if (!$this->termineAt || !$this->aFaireAt) {
            return null;
        }
        return $this->termineAt->getTimestamp() - $this->aFaireAt->getTimestamp();
    }

    public function getAFaireToDevsTerminesTime(): ?int
    {
        if (!$this->devsTerminesAt || !$this->aFaireAt) {
            return null;
        }
        return $this->devsTerminesAt->getTimestamp() - $this->aFaireAt->getTimestamp();
    }
} 