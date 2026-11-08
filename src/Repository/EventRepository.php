<?php

namespace App\Repository;


use App\Entity\Event;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    //    /**
    //     * @return Event[] Returns an array of Event objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Event
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Find the next upcoming event.
     */
    public function findNextEvent(): ?Event
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->andWhere('e.date > :now')
            ->setParameter('status', 'upcoming')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.date', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Event[] rencontres a venir, les plus proches d'abord */
    public function findUpcoming(int $limit = 50): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.date >= :now')->setParameter('now', new \DateTime('today'))->andWhere('e.status NOT IN (:hidden)')->setParameter('hidden', ['pending', 'rejected'])
            ->orderBy('e.date', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }

    /** @return Event[] rencontres passees, les plus recentes d'abord */
    public function findPast(int $limit = 20): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.date < :now')->setParameter('now', new \DateTime('today'))->andWhere('e.status NOT IN (:hidden)')->setParameter('hidden', ['pending', 'rejected'])
            ->orderBy('e.date', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }
}
