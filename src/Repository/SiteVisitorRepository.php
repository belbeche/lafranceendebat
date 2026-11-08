<?php
// src/Repository/SiteVisitorRepository.php

namespace App\Repository;

use App\Entity\SiteVisitor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteVisitor>
 */
class SiteVisitorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteVisitor::class);
    }

    public function getTodayVisitorsCount(): int
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.ipAddress)')
            ->where('v.visitedAt >= :today')
            ->andWhere('v.visitedAt < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalUniqueVisitors(): int
    {
        return $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.ipAddress)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getVisitorsCountSince(\DateTimeInterface $startDate, \DateTimeInterface $endDate = null): int
    {
        $qb = $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.ipAddress)')
            ->where('v.visitedAt >= :startDate')
            ->setParameter('startDate', $startDate);

        if ($endDate) {
            $qb->andWhere('v.visitedAt < :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getVisitorsLast30Days(): array
    {
        $startDate = new \DateTimeImmutable('-30 days');

        // Utilisation de la connexion SQL native pour les fonctions de date
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                DATE(visited_at) as date, 
                COUNT(DISTINCT ip_address) as visitors
            FROM site_visitor 
            WHERE visited_at >= :startDate
            GROUP BY DATE(visited_at)
            ORDER BY date ASC
        ";

        return $conn->executeQuery($sql, [
            'startDate' => $startDate->format('Y-m-d H:i:s')
        ])->fetchAllAssociative();
    }

    public function getPopularPages(): array
    {
        return $this->createQueryBuilder('v')
            ->select('v.pageVisited as page, COUNT(v.id) as visits')
            ->where('v.pageVisited IS NOT NULL')
            ->groupBy('v.pageVisited')
            ->orderBy('visits', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    public function getHourlyStatsForToday(): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT 
                HOUR(visited_at) as hour,
                COUNT(DISTINCT ip_address) as visitors
            FROM site_visitor 
            WHERE visited_at >= :today AND visited_at < :tomorrow
            GROUP BY HOUR(visited_at)
            ORDER BY hour ASC
        ";

        return $conn->executeQuery($sql, [
            'today' => $today->format('Y-m-d H:i:s'),
            'tomorrow' => $tomorrow->format('Y-m-d H:i:s')
        ])->fetchAllAssociative();
    }

    /**
     * Nettoie les visites de plus de X jours
     */
    public function cleanupOldVisits(int $days = 90): int
    {
        $cutoffDate = new \DateTimeImmutable("-$days days");
        
        return $this->createQueryBuilder('v')
            ->delete()
            ->where('v.visitedAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    public function getVisitorsByCountry(): array
    {
        return $this->createQueryBuilder('v')
            ->select('v.countryCode as country, COUNT(DISTINCT v.ipAddress) as visitors')
            ->where('v.countryCode IS NOT NULL')
            ->groupBy('v.countryCode')
            ->orderBy('visitors', 'DESC')
            ->getQuery()
            ->getResult();
    }
}