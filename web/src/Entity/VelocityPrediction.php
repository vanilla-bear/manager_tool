<?php

namespace App\Entity;

use App\Repository\VelocityPredictionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VelocityPredictionRepository::class)]
class VelocityPrediction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $predictedVelocity = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $confidence = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $trend = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $seasonality = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $teamCapacity = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $historicalAverage = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $completionProbability = 0.0;

    #[ORM\Column(type: Types::JSON)]
    private array $riskFactors = [];

    #[ORM\Column(type: Types::JSON)]
    private array $recommendations = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $predictionDate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $targetSprintStart = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $targetSprintEnd = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $actualVelocity = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $accuracy = null;

    public function __construct()
    {
        $this->predictionDate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPredictedVelocity(): float
    {
        return $this->predictedVelocity;
    }

    public function setPredictedVelocity(float $predictedVelocity): static
    {
        $this->predictedVelocity = $predictedVelocity;
        return $this;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function setConfidence(float $confidence): static
    {
        $this->confidence = $confidence;
        return $this;
    }

    public function getTrend(): float
    {
        return $this->trend;
    }

    public function setTrend(float $trend): static
    {
        $this->trend = $trend;
        return $this;
    }

    public function getSeasonality(): float
    {
        return $this->seasonality;
    }

    public function setSeasonality(float $seasonality): static
    {
        $this->seasonality = $seasonality;
        return $this;
    }

    public function getTeamCapacity(): float
    {
        return $this->teamCapacity;
    }

    public function setTeamCapacity(float $teamCapacity): static
    {
        $this->teamCapacity = $teamCapacity;
        return $this;
    }

    public function getHistoricalAverage(): float
    {
        return $this->historicalAverage;
    }

    public function setHistoricalAverage(float $historicalAverage): static
    {
        $this->historicalAverage = $historicalAverage;
        return $this;
    }

    public function getCompletionProbability(): float
    {
        return $this->completionProbability;
    }

    public function setCompletionProbability(float $completionProbability): static
    {
        $this->completionProbability = $completionProbability;
        return $this;
    }

    public function getRiskFactors(): array
    {
        return $this->riskFactors;
    }

    public function setRiskFactors(array $riskFactors): static
    {
        $this->riskFactors = $riskFactors;
        return $this;
    }

    public function getRecommendations(): array
    {
        return $this->recommendations;
    }

    public function setRecommendations(array $recommendations): static
    {
        $this->recommendations = $recommendations;
        return $this;
    }

    public function getPredictionDate(): ?\DateTimeImmutable
    {
        return $this->predictionDate;
    }

    public function setPredictionDate(\DateTimeImmutable $predictionDate): static
    {
        $this->predictionDate = $predictionDate;
        return $this;
    }

    public function getTargetSprintStart(): ?\DateTimeImmutable
    {
        return $this->targetSprintStart;
    }

    public function setTargetSprintStart(?\DateTimeImmutable $targetSprintStart): static
    {
        $this->targetSprintStart = $targetSprintStart;
        return $this;
    }

    public function getTargetSprintEnd(): ?\DateTimeImmutable
    {
        return $this->targetSprintEnd;
    }

    public function setTargetSprintEnd(?\DateTimeImmutable $targetSprintEnd): static
    {
        $this->targetSprintEnd = $targetSprintEnd;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getActualVelocity(): ?float
    {
        return $this->actualVelocity;
    }

    public function setActualVelocity(?float $actualVelocity): static
    {
        $this->actualVelocity = $actualVelocity;
        return $this;
    }

    public function getAccuracy(): ?float
    {
        return $this->accuracy;
    }

    public function setAccuracy(?float $accuracy): static
    {
        $this->accuracy = $accuracy;
        return $this;
    }

    /**
     * Calcule la précision de la prédiction
     */
    public function calculateAccuracy(): ?float
    {
        if ($this->actualVelocity === null) {
            return null;
        }

        $error = abs($this->predictedVelocity - $this->actualVelocity);
        $accuracy = max(0, 1 - ($error / max($this->predictedVelocity, $this->actualVelocity, 1)));
        
        $this->accuracy = $accuracy;
        return $accuracy;
    }

    /**
     * Vérifie si la prédiction est expirée
     */
    public function isExpired(): bool
    {
        if (!$this->targetSprintEnd) {
            return false;
        }

        return new \DateTimeImmutable() > $this->targetSprintEnd;
    }

    /**
     * Marque la prédiction comme inactive
     */
    public function deactivate(): static
    {
        $this->isActive = false;
        return $this;
    }
}

