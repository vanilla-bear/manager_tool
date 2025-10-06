<?php

namespace App\Repository;

use App\Entity\VelocityPrediction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VelocityPrediction>
 */
class VelocityPredictionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VelocityPrediction::class);
    }

    /**
     * Trouve la prédiction active la plus récente
     */
    public function findLatestActive(): ?VelocityPrediction
    {
        return $this->createQueryBuilder('vp')
            ->where('vp.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('vp.predictionDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les prédictions actives
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('vp')
            ->where('vp.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('vp.predictionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prédictions par période
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('vp')
            ->where('vp.predictionDate BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('vp.predictionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prédictions avec une précision calculée
     */
    public function findWithAccuracy(): array
    {
        return $this->createQueryBuilder('vp')
            ->where('vp.accuracy IS NOT NULL')
            ->orderBy('vp.predictionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule la précision moyenne des prédictions
     */
    public function getAverageAccuracy(): float
    {
        $result = $this->createQueryBuilder('vp')
            ->select('AVG(vp.accuracy) as avg_accuracy')
            ->where('vp.accuracy IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (float) $result : 0.0;
    }

    /**
     * Trouve les prédictions expirées
     */
    public function findExpired(): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('vp')
            ->where('vp.targetSprintEnd < :now')
            ->setParameter('now', $now)
            ->orderBy('vp.predictionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Désactive les prédictions expirées
     */
    public function deactivateExpired(): int
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('vp')
            ->update()
            ->set('vp.isActive', ':inactive')
            ->where('vp.targetSprintEnd < :now')
            ->andWhere('vp.isActive = :active')
            ->setParameter('inactive', false)
            ->setParameter('now', $now)
            ->setParameter('active', true)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les prédictions par niveau de confiance
     */
    public function findByConfidenceRange(float $minConfidence, float $maxConfidence): array
    {
        return $this->createQueryBuilder('vp')
            ->where('vp.confidence BETWEEN :minConfidence AND :maxConfidence')
            ->setParameter('minConfidence', $minConfidence)
            ->setParameter('maxConfidence', $maxConfidence)
            ->orderBy('vp.predictionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prédictions avec des facteurs de risque
     */
    public function findWithRiskFactors(): array
    {
        return $this->createQueryBuilder('vp')
            ->where('JSON_LENGTH(vp.riskFactors) > 0')
            ->orderBy('vp.predictionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les statistiques des prédictions
     */
    public function getPredictionStats(): array
    {
        $qb = $this->createQueryBuilder('vp');
        
        $stats = $qb
            ->select([
                'COUNT(vp.id) as total_predictions',
                'AVG(vp.confidence) as avg_confidence',
                'AVG(vp.accuracy) as avg_accuracy',
                'MIN(vp.predictionDate) as first_prediction',
                'MAX(vp.predictionDate) as last_prediction'
            ])
            ->getQuery()
            ->getSingleResult();

        return [
            'total_predictions' => (int) $stats['total_predictions'],
            'average_confidence' => $stats['avg_confidence'] ? round((float) $stats['avg_confidence'], 2) : 0,
            'average_accuracy' => $stats['avg_accuracy'] ? round((float) $stats['avg_accuracy'], 2) : 0,
            'first_prediction' => $stats['first_prediction'],
            'last_prediction' => $stats['last_prediction']
        ];
    }
}

