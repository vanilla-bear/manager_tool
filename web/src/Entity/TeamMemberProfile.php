<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\TeamMemberProfileRepository;

#[ORM\Entity(repositoryClass: TeamMemberProfileRepository::class)]
class TeamMemberProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: TeamMember::class)]
    #[ORM\JoinColumn(nullable: false)]
    private TeamMember $teamMember;

    #[ORM\Column(type: 'json')]
    private array $productivityStats = [];

    #[ORM\Column(type: 'json')]
    private array $qualityStats = [];

    #[ORM\Column(type: 'json')]
    private array $impactStats = [];

    #[ORM\Column(type: 'json')]
    private array $collaborationStats = [];

    #[ORM\Column(type: 'json')]
    private array $evolutionStats = [];

    #[ORM\Column(type: 'json')]
    private array $qualitativeFeedback = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $analysisDate;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $periodStart;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $periodEnd;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $lastSyncAt;

    public function __construct()
    {
        $this->analysisDate = new \DateTimeImmutable();
        $this->lastSyncAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeamMember(): TeamMember
    {
        return $this->teamMember;
    }

    public function setTeamMember(TeamMember $teamMember): self
    {
        $this->teamMember = $teamMember;
        return $this;
    }

    public function getProductivityStats(): array
    {
        return $this->productivityStats;
    }

    public function setProductivityStats(array $productivityStats): self
    {
        $this->productivityStats = $productivityStats;
        return $this;
    }

    public function getQualityStats(): array
    {
        return $this->qualityStats;
    }

    public function setQualityStats(array $qualityStats): self
    {
        $this->qualityStats = $qualityStats;
        return $this;
    }

    public function getImpactStats(): array
    {
        return $this->impactStats;
    }

    public function setImpactStats(array $impactStats): self
    {
        $this->impactStats = $impactStats;
        return $this;
    }

    public function getCollaborationStats(): array
    {
        return $this->collaborationStats;
    }

    public function setCollaborationStats(array $collaborationStats): self
    {
        $this->collaborationStats = $collaborationStats;
        return $this;
    }

    public function getEvolutionStats(): array
    {
        return $this->evolutionStats;
    }

    public function setEvolutionStats(array $evolutionStats): self
    {
        $this->evolutionStats = $evolutionStats;
        return $this;
    }

    public function getQualitativeFeedback(): array
    {
        return $this->qualitativeFeedback;
    }

    public function setQualitativeFeedback(array $qualitativeFeedback): self
    {
        $this->qualitativeFeedback = $qualitativeFeedback;
        return $this;
    }

    public function getAnalysisDate(): \DateTimeImmutable
    {
        return $this->analysisDate;
    }

    public function setAnalysisDate(\DateTimeImmutable $analysisDate): self
    {
        $this->analysisDate = $analysisDate;
        return $this;
    }

    public function getPeriodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeImmutable $periodStart): self
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeImmutable $periodEnd): self
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getLastSyncAt(): \DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(\DateTimeImmutable $lastSyncAt): self
    {
        $this->lastSyncAt = $lastSyncAt;
        return $this;
    }

    /**
     * Retourne toutes les statistiques sous forme de tableau
     */
    public function getAllStats(): array
    {
        return [
            'teamMember' => [
                'id' => $this->teamMember->getId(),
                'name' => $this->teamMember->getName(),
                'jiraId' => $this->teamMember->getJiraId(),
            ],
            'period' => [
                'start' => $this->periodStart->format('Y-m-d'),
                'end' => $this->periodEnd->format('Y-m-d'),
                'analysisDate' => $this->analysisDate->format('Y-m-d H:i:s'),
                'lastSyncAt' => $this->lastSyncAt->format('Y-m-d H:i:s'),
            ],
            'productivity' => $this->productivityStats,
            'quality' => $this->qualityStats,
            'impact' => $this->impactStats,
            'collaboration' => $this->collaborationStats,
            'evolution' => $this->evolutionStats,
            'qualitativeFeedback' => $this->qualitativeFeedback,
        ];
    }
} 