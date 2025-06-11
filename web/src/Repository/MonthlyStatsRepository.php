<?php

namespace App\Repository;

use App\Entity\MonthlyStats;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MonthlyStatsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthlyStats::class);
    }

    public function save(MonthlyStats $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByMonth(\DateTimeInterface $date): ?MonthlyStats
    {
        $startOfMonth = new \DateTimeImmutable($date->format('Y-m-01'));
        
        return $this->createQueryBuilder('m')
            ->andWhere('m.month = :month')
            ->setParameter('month', $startOfMonth)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastMonths(int $count = 3): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.month', 'DESC')
            ->setMaxResults($count)
            ->getQuery()
            ->getResult();
    }

    public function getAverageBugRate(int $months = 3): ?float
    {
        $stats = $this->findLastMonths($months);
        if (empty($stats)) {
            return null;
        }

        $totalBugs = 0;
        $totalDelivered = 0;
        
        /** @var MonthlyStats $stat */
        foreach ($stats as $stat) {
            $totalBugs += $stat->getBugsCount();
            $totalDelivered += $stat->getDeliveredTicketsCount();
        }

        if ($totalDelivered === 0) {
            return null;
        }

        return $totalBugs / $totalDelivered;
    }

    public function getAverageMonthlyBugs(int $months = 3): ?float
    {
        $stats = $this->findLastMonths($months);
        if (empty($stats)) {
            return null;
        }

        $totalBugs = 0;
        foreach ($stats as $stat) {
            $totalBugs += $stat->getBugsCount();
        }

        return $totalBugs / count($stats);
    }
} 