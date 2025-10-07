<?php

namespace App\Repository;

use App\Entity\TimePeriod;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TimePeriodRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, TimePeriod::class);
  }

  public function findByCriteria(array $criteria, int $page = 1, int $limit = 20)
  {
    $qb = $this->createQueryBuilder('t');

    if (!empty($criteria['name'])) {
      $qb->andWhere('t.name LIKE :name')
        ->setParameter('name', '%' . $criteria['name'] . '%');
    }

    if (!empty($criteria['type'])) {
      $qb->andWhere('t.type = :type')
        ->setParameter('type', $criteria['type']);
    }

    // Tri par date de création décroissante (plus récents en premier)
    $qb->orderBy('t.id', 'DESC');

    // Pagination
    $offset = ($page - 1) * $limit;
    $qb->setFirstResult($offset)
       ->setMaxResults($limit);

    return $qb->getQuery()->getResult();
  }

  public function countByCriteria(array $criteria): int
  {
    $qb = $this->createQueryBuilder('t')
      ->select('COUNT(t.id)');

    if (!empty($criteria['name'])) {
      $qb->andWhere('t.name LIKE :name')
        ->setParameter('name', '%' . $criteria['name'] . '%');
    }

    if (!empty($criteria['type'])) {
      $qb->andWhere('t.type = :type')
        ->setParameter('type', $criteria['type']);
    }

    return $qb->getQuery()->getSingleScalarResult();
  }

}
