<?php

namespace App\Repository;

use App\Entity\Sprint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sprint>
 */
class SprintRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sprint::class);
    }

    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.startDate <= :end')
            ->andWhere('s.endDate >= :start')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLastSprints(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.endDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByJiraId(int $jiraId): ?Sprint
    {
        return $this->findOneBy(['jiraId' => $jiraId]);
    }

    public function getAverageVelocity(int $sprintCount = 3): float
    {
        $sprints = $this->findLastSprints($sprintCount);
        
        if (empty($sprints)) {
            return 0.0;
        }

        $totalVelocity = 0;
        $count = 0;

        foreach ($sprints as $sprint) {
            $velocityPerDay = $sprint->getVelocityPerDay();
            if ($velocityPerDay !== null) {
                $totalVelocity += $velocityPerDay;
                $count++;
            }
        }

        return $count > 0 ? $totalVelocity / $count : 0.0;
    }

    public function save(Sprint $sprint, bool $flush = false): void
    {
        $this->getEntityManager()->persist($sprint);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Sprint $sprint, bool $flush = false): void
    {
        $this->getEntityManager()->remove($sprint);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findCurrentSprint(): ?Sprint
    {
        $now = new \DateTime();
        
        // D'abord, chercher un sprint actif (en cours)
        $activeSprint = $this->createQueryBuilder('s')
            ->andWhere('s.startDate <= :now')
            ->andWhere('s.endDate >= :now')
            ->setParameter('now', $now)
            ->orderBy('s.startDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
            
        if ($activeSprint) {
            return $activeSprint;
        }
        
        // Si aucun sprint actif, retourner le plus récent
        return $this->createQueryBuilder('s')
            ->orderBy('s.endDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
} 