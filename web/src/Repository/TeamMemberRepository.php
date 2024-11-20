<?php

namespace App\Repository;

use App\Entity\TeamMember;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TeamMemberRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, TeamMember::class);
  }

  public function findByName(string $name): array
  {
    return $this->createQueryBuilder('t')
      ->where('t.name LIKE :name')
      ->setParameter('name', '%' . $name . '%')
      ->getQuery()
      ->getResult();
  }

  public function countMembers(): int
  {
    return (int) $this->createQueryBuilder('t')
      ->select('COUNT(t.id)')
      ->getQuery()
      ->getSingleScalarResult();
  }

}
