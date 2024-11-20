<?php

namespace App\Repository;

use App\Entity\GoogleCalendarEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoogleCalendarEvent>
 *
 * @method GoogleCalendarEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method GoogleCalendarEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method GoogleCalendarEvent[]    findAll()
 * @method GoogleCalendarEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GoogleCalendarEventRepository extends ServiceEntityRepository
{
  public function __construct(ManagerRegistry $registry)
  {
    parent::__construct($registry, GoogleCalendarEvent::class);
  }

  /**
   * Finds all events within a specific date range.
   *
   * @param \DateTimeInterface $startDate
   * @param \DateTimeInterface $endDate
   * @return GoogleCalendarEvent[]
   */
  public function findEventsWithinDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
  {
    return $this->createQueryBuilder('e')
      ->where('e.startTime >= :startDate')
      ->andWhere('e.endTime <= :endDate')
      ->setParameter('startDate', $startDate)
      ->setParameter('endDate', $endDate)
      ->orderBy('e.startTime', 'ASC')
      ->getQuery()
      ->getResult();
  }

  /**
   * Finds an event by its unique Google Calendar ID.
   *
   * @param string $eventId
   * @return GoogleCalendarEvent|null
   */
  public function findOneByEventId(string $eventId): ?GoogleCalendarEvent
  {
    return $this->findOneBy(['eventId' => $eventId]);
  }
}
