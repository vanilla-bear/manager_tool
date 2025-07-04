<?php

namespace App\Repository;

use App\Entity\TeamMemberProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamMemberProfile>
 */
class TeamMemberProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMemberProfile::class);
    }

    public function findByTeamMember(int $teamMemberId): ?TeamMemberProfile
    {
        return $this->findOneBy(['teamMember' => $teamMemberId]);
    }

    public function findLatestByTeamMember(int $teamMemberId): ?TeamMemberProfile
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.teamMember = :teamMemberId')
            ->setParameter('teamMemberId', $teamMemberId)
            ->orderBy('p.lastSyncAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(TeamMemberProfile $profile, bool $flush = false): void
    {
        $this->getEntityManager()->persist($profile);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
} 