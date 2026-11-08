<?php

namespace App\Repository;

use App\Entity\NewsletterCampaign;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterCampaign>
 *
 * @method NewsletterCampaign|null find($id, $lockMode = null, $lockVersion = null)
 * @method NewsletterCampaign|null findOneBy(array $criteria, array $orderBy = null)
 * @method NewsletterCampaign[]    findAll()
 * @method NewsletterCampaign[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */

/**
 * Dépôt Doctrine (requêtes d'une entité) « NewsletterCampaignRepository ».
 *
 * Entité gérée : NewsletterCampaign.
 */
class NewsletterCampaignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterCampaign::class);
    }

    public function save(NewsletterCampaign $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NewsletterCampaign $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les campagnes en attente de traitement
     */
    public function findPendingCampaigns(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les campagnes en cours de traitement
     */
    public function findProcessingCampaigns(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'processing')
            ->orderBy('c.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les campagnes terminées
     */
    public function findCompletedCampaigns(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'completed')
            ->orderBy('c.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les campagnes ayant échoué
     */
    public function findFailedCampaigns(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', 'failed')
            ->orderBy('c.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une campagne par son ID avec verrouillage
     */
    public function findOneByIdWithLock(int $id): ?NewsletterCampaign
    {
        return $this->createQueryBuilder('c')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /**
     * Statistiques des campagnes
     */
    public function getCampaignStats(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select([
                'COUNT(c.id) as total',
                'SUM(CASE WHEN c.status = :pending THEN 1 ELSE 0 END) as pending',
                'SUM(CASE WHEN c.status = :processing THEN 1 ELSE 0 END) as processing',
                'SUM(CASE WHEN c.status = :completed THEN 1 ELSE 0 END) as completed',
                'SUM(CASE WHEN c.status = :failed THEN 1 ELSE 0 END) as failed',
                'COALESCE(SUM(c.totalRecipients), 0) as total_recipients',
                'COALESCE(SUM(c.sentCount), 0) as total_sent',
                'COALESCE(SUM(c.failedCount), 0) as total_failed'
            ])
            ->setParameter('pending', 'pending')
            ->setParameter('processing', 'processing')
            ->setParameter('completed', 'completed')
            ->setParameter('failed', 'failed');

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'pending' => (int) $result['pending'],
            'processing' => (int) $result['processing'],
            'completed' => (int) $result['completed'],
            'failed' => (int) $result['failed'],
            'total_recipients' => (int) $result['total_recipients'],
            'total_sent' => (int) $result['total_sent'],
            'total_failed' => (int) $result['total_failed'],
            'success_rate' => $result['total_sent'] > 0 ? 
                round(($result['total_sent'] / max($result['total_recipients'], 1)) * 100, 2) : 0
        ];
    }

    /**
     * Campagnes récentes avec pagination
     */
    public function findRecentCampaigns(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre total de campagnes avec filtre optionnel
     */
    public function countCampaigns(?string $status = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)');

        if ($status) {
            $qb->where('c.status = :status')
                ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Campagnes qui ont démarré mais n'ont pas été mises à jour depuis X minutes
     * (utile pour détecter les campagnes bloquées)
     */
    public function findStalledCampaigns(int $minutes = 30): array
    {
        $cutoff = new \DateTimeImmutable("-{$minutes} minutes");

        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.lastUpdate < :cutoff OR c.lastUpdate IS NULL')
            ->setParameter('status', 'processing')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }

    /**
     * Mettre à jour le statut d'une campagne
     */
    public function updateCampaignStatus(int $id, string $status, ?string $error = null): bool
    {
        $campaign = $this->find($id);
        
        if (!$campaign) {
            return false;
        }

        $campaign->setStatus($status);
        
        if ($status === 'completed' || $status === 'failed') {
            $campaign->setCompletedAt(new \DateTimeImmutable());
        }
        
        if ($error) {
            $campaign->setError($error);
        }

        $campaign->setLastUpdate(new \DateTimeImmutable());
        
        $this->getEntityManager()->flush();
        
        return true;
    }

    /**
     * Incrémenter les compteurs d'une campagne
     */
    public function incrementCounters(int $id, int $sent = 0, int $failed = 0): bool
    {
        $campaign = $this->find($id);
        
        if (!$campaign) {
            return false;
        }

        if ($sent > 0) {
            $campaign->setSentCount($campaign->getSentCount() + $sent);
        }
        
        if ($failed > 0) {
            $campaign->setFailedCount($campaign->getFailedCount() + $failed);
        }

        $campaign->setLastUpdate(new \DateTimeImmutable());
        
        $this->getEntityManager()->flush();
        
        return true;
    }

    /**
     * Trouver les campagnes pour un rapport mensuel
     */
    public function findMonthlyReport(int $year, int $month): array
    {
        $startDate = new \DateTimeImmutable("{$year}-{$month}-01");
        $endDate = $startDate->modify('last day of this month')->setTime(23, 59, 59);

        return $this->createQueryBuilder('c')
            ->where('c.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nettoyer les anciennes campagnes (maintenance)
     */
    public function cleanupOldCampaigns(int $daysToKeep = 90): int
    {
        $cutoff = new \DateTimeImmutable("-{$daysToKeep} days");

        $qb = $this->createQueryBuilder('c')
            ->delete()
            ->where('c.status = :completed')
            ->andWhere('c.completedAt < :cutoff')
            ->setParameter('completed', 'completed')
            ->setParameter('cutoff', $cutoff);

        return $qb->getQuery()->execute();
    }

    public function findScheduledCampaigns(): array
    {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('(c.scheduledFor IS NOT NULL AND c.scheduledFor <= :now) OR (c.scheduledFor IS NULL)')
            ->setParameter('status', 'pending')
            ->setParameter('now', $now)
            ->orderBy('c.priority', 'DESC')
            ->addOrderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
    
    public function findCampaignsToStart(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.status = :pendingStatus')
            ->setParameter('pendingStatus', 'pending');
        
        // Récupérer toutes les campagnes en attente qui n'ont pas de date planifiée
        // OU dont la date planifiée est passée
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        
        $qb->andWhere(
            $qb->expr()->orX(
                $qb->expr()->isNull('c.scheduledFor'),
                $qb->expr()->lte('c.scheduledFor', ':now')
            )
        )
        ->setParameter('now', $now);
        
        return $qb->getQuery()->getResult();
    }

    public function getActiveSubscriberIdsChunked(int $chunkSize = 10000): array
    {
        $connection = $this->getEntityManager()->getConnection();
        
        $ids = [];
        $offset = 0;
        
        do {
            $sql = "SELECT id FROM newsletter_subscriber 
                    WHERE is_active = 1 
                    ORDER BY id 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $connection->prepare($sql);
            $stmt->bindValue('limit', $chunkSize, \PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
            $result = $stmt->executeQuery();
            
            $chunkIds = $result->fetchFirstColumn();
            
            if (empty($chunkIds)) {
                break;
            }
            
            $ids = array_merge($ids, $chunkIds);
            $offset += $chunkSize;
            
            // Libérer la mémoire
            unset($chunkIds);
            gc_collect_cycles();
            
        } while (true);
        
        return $ids;
    }

//    /**
//     * @return NewsletterCampaign[] Returns an array of NewsletterCampaign objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('n.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?zzeeNewsletterCampaign
//    {
//        return $this->createQueryBuilder('n')
//            ->andWhere('n.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}