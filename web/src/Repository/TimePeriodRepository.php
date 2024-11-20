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

  public function findByCriteria(array $criteria)
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

    return $qb->getQuery()->getResult();
  }

}
