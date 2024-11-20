<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\GoogleCalendarEventRepository;

#[ORM\Entity(repositoryClass: GoogleCalendarEventRepository::class)]
class GoogleCalendarEvent
{
  #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
  private $id;

  #[ORM\Column(type: 'string', length: 255)]
  private $eventId;

  #[ORM\Column(type: 'string', length: 255)]
  private $title;

  #[ORM\Column(type: 'datetime')]
  private $startTime;

  #[ORM\Column(type: 'datetime')]
  private $endTime;

  #[ORM\Column(type: 'text', nullable: true)]
  private $description;

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getEventId(): ?string
  {
    return $this->eventId;
  }

  public function setEventId(string $eventId): self
  {
    $this->eventId = $eventId;
    return $this;
  }

  public function getTitle(): ?string
  {
    return $this->title;
  }

  public function setTitle(string $title): self
  {
    $this->title = $title;
    return $this;
  }

  public function getStartTime(): ?\DateTimeInterface
  {
    return $this->startTime;
  }

  public function setStartTime(\DateTimeInterface $startTime): self
  {
    $this->startTime = $startTime;
    return $this;
  }

  public function getEndTime(): ?\DateTimeInterface
  {
    return $this->endTime;
  }

  public function setEndTime(\DateTimeInterface $endTime): self
  {
    $this->endTime = $endTime;
    return $this;
  }

  public function getDescription(): ?string
  {
    return $this->description;
  }

  public function setDescription(?string $description): self
  {
    $this->description = $description;
    return $this;
  }
}
