<?php

namespace App\Repository;

use App\Entity\BugMTTR;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BugMTTR>
 *
 * @method BugMTTR|null find($id, $lockMode = null, $lockVersion = null)
 * @method BugMTTR|null findOneBy(array $criteria, array $orderBy = null)
 * @method BugMTTR[]    findAll()
 * @method BugMTTR[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BugMTTRRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BugMTTR::class);
    }

    public function save(BugMTTR $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function getLastSyncedBug(): ?BugMTTR
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.bugKey', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getAverageMTTR(int $days = 90): array
    {
        $date = new \DateTime("-{$days} days");
        
        // Récupérer tous les bugs résolus dans la période
        $bugs = $this->createQueryBuilder('b')
            ->where('b.createdAt >= :date')
            ->andWhere('b.termineAt IS NOT NULL')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        $stats = [
            'totalCreatedToTermine' => 0,
            'totalAFaireToTermine' => 0,
            'totalAFaireToDevsTermines' => 0,
            'countCreatedToTermine' => 0,
            'countAFaireToTermine' => 0,
            'countAFaireToDevsTermines' => 0,
            'totalBugs' => count($bugs),
            'totalResolved' => 0,
            'minCreatedToTermine' => PHP_INT_MAX,
            'maxCreatedToTermine' => 0,
            'details' => []
        ];

        foreach ($bugs as $bug) {
            if ($bug->getTermineAt()) {
                $createdToTermine = $bug->getCreatedToTermineTime();
                if ($createdToTermine !== null) {
                    $stats['totalCreatedToTermine'] += $createdToTermine;
                    $stats['countCreatedToTermine']++;
                    $stats['totalResolved']++;
                    
                    // Garder les min/max
                    $stats['minCreatedToTermine'] = min($stats['minCreatedToTermine'], $createdToTermine);
                    $stats['maxCreatedToTermine'] = max($stats['maxCreatedToTermine'], $createdToTermine);
                    
                    // Garder les détails pour l'analyse
                    $stats['details'][] = [
                        'bugKey' => $bug->getBugKey(),
                        'createdToTermine' => $createdToTermine / 86400, // en jours
                        'summary' => $bug->getSummary()
                    ];
                }

                if ($bug->getAFaireToTermineTime() !== null) {
                    $stats['totalAFaireToTermine'] += $bug->getAFaireToTermineTime();
                    $stats['countAFaireToTermine']++;
                }
            }

            if ($bug->getAFaireToDevsTerminesTime() !== null) {
                $stats['totalAFaireToDevsTermines'] += $bug->getAFaireToDevsTerminesTime();
                $stats['countAFaireToDevsTermines']++;
            }
        }

        // Calculer les moyennes
        $avgCreatedToTermine = $stats['countCreatedToTermine'] > 0 
            ? $stats['totalCreatedToTermine'] / $stats['countCreatedToTermine']
            : null;
            
        $avgAFaireToTermine = $stats['countAFaireToTermine'] > 0
            ? $stats['totalAFaireToTermine'] / $stats['countAFaireToTermine']
            : null;
            
        $avgAFaireToDevsTermines = $stats['countAFaireToDevsTermines'] > 0
            ? $stats['totalAFaireToDevsTermines'] / $stats['countAFaireToDevsTermines']
            : null;

        // Trier les détails par temps de résolution
        if (!empty($stats['details'])) {
            usort($stats['details'], function($a, $b) {
                return $b['createdToTermine'] <=> $a['createdToTermine'];
            });
        }

        return [
            'avgCreatedToTermine' => $avgCreatedToTermine,
            'avgAFaireToTermine' => $avgAFaireToTermine,
            'avgAFaireToDevsTermines' => $avgAFaireToDevsTermines,
            'totalBugs' => $stats['totalBugs'],
            'totalResolved' => $stats['totalResolved'],
            'minCreatedToTermine' => $stats['minCreatedToTermine'] === PHP_INT_MAX ? null : $stats['minCreatedToTermine'],
            'maxCreatedToTermine' => $stats['maxCreatedToTermine'] ?: null,
            'details' => array_slice($stats['details'], 0, 5) // Top 5 des bugs les plus longs
        ];
    }

    public function getPaginatedBugs(int $page = 1, int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countBugs(): int
    {
        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Met à jour les statistiques d'un bug existant
     */
    public function updateBug(BugMTTR $bug): void
    {
        // Si le bug est terminé, on met à jour ses statistiques
        if ($bug->getCurrentStatus() === 'Terminé' && $bug->getTermineAt() === null) {
            $bug->setTermineAt(new \DateTimeImmutable());
        }
        
        $this->save($bug, true);
    }
} 