<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\TeamMemberRepository;

#[ORM\Entity(repositoryClass: TeamMemberRepository::class)]
class TeamMember
{
  public const WORKING_HOURS_PER_DAY = 7.5;

  #[ORM\Id]
  #[ORM\GeneratedValue]
  #[ORM\Column(type: 'integer')]
  private int $id;

  #[ORM\Column(type: 'string', length: 255)]
  private string $name;

  #[ORM\Column(type: 'string', length: 255, nullable: true)]
  private ?string $jiraId = null;

  // Additional fields and methods...

  public function getId(): ?int
  {
    return $this->id;
  }

  public function getName(): ?string
  {
    return $this->name;
  }

  public function setName(string $name): self
  {
    $this->name = $name;
    return $this;
  }

  public function getJiraId(): ?string
  {
    return $this->jiraId;
  }

  public function setJiraId(?string $jiraId): self
  {
    $this->jiraId = $jiraId;
    return $this;
  }

  public function getWorkingHoursPerDay(): int
  {
    return self::WORKING_HOURS_PER_DAY;
  }
}
