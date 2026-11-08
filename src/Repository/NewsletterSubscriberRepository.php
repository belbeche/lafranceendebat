<?php

namespace App\Repository;

use App\Entity\ImportLog;
use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<NewsletterSubscriber>
 */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    public function findByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->createQueryBuilder('n')
            ->where('n.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les abonnés actifs
     */
    public function findActiveSubscribers(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.subscribedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les abonnés récents
     */
    public function findRecentSubscribers(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.subscribedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les abonnés qui ne sont pas dans une campagne
     */
    public function findNotInCampaign(NewsletterCampaign $campaign): array
    {
        $subscribersInCampaign = $campaign->getSubscribers()->map(function ($subscriber) {
            return $subscriber->getId();
        })->toArray();

        $qb = $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true);

        if (!empty($subscribersInCampaign)) {
            $qb->andWhere('s.id NOT IN (:subscribersInCampaign)')
                ->setParameter('subscribersInCampaign', $subscribersInCampaign);
        }

        return $qb->orderBy('s.subscribedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtient les statistiques d'abonnement
     */
    public function getSubscriptionStatistics(): array
    {
        $currentMonth = new \DateTime('first day of this month');
        $previousMonth = new \DateTime('first day of last month');

        $currentMonthCount = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.subscribedAt >= :startDate')
            ->andWhere('s.isActive = :active')
            ->setParameter('startDate', $currentMonth)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $previousMonthCount = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.subscribedAt >= :startDate AND s.subscribedAt < :endDate')
            ->andWhere('s.isActive = :active')
            ->setParameter('startDate', $previousMonth)
            ->setParameter('endDate', $currentMonth)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'current_month' => $currentMonthCount,
            'previous_month' => $previousMonthCount,
            'growth' => $previousMonthCount > 0 ?
                (($currentMonthCount - $previousMonthCount) / $previousMonthCount) * 100 : 0,
        ];
    }

    public function count(array $criteria = []): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)');

        if (!empty($criteria)) {
            $i = 0;
            foreach ($criteria as $field => $value) {
                $qb->andWhere("s.$field = :value$i")
                   ->setParameter("value$i", $value);
                $i++;
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findUniqueCampaigns(): array
    {
        $queryBuilder = $this->createQueryBuilder('s')
            ->select('DISTINCT s.campaignName')
            ->where('s.campaignName IS NOT NULL')
            ->orderBy('s.campaignName', 'ASC');

        return array_column($queryBuilder->getQuery()->getResult(), 'campaignName');
    }

    /**
     * Trouve les abonnés d'une campagne spécifique
     */
    public function findByCampaign(NewsletterCampaign $campaign, ?string $search = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.campaigns', 'c')
            ->where('c.id = :campaignId')
            ->setParameter('campaignId', $campaign->getId());
        
        if ($search) {
            $qb->andWhere('s.email LIKE :search OR s.firstName LIKE :search OR s.lastName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }
        
        return $qb->orderBy('s.email', 'ASC');
    }

    /**
     * Amélioration : Recherche avancée
     */
    public function searchSubscribers(?string $query = null, ?array $filters = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('s');

        if ($query) {
            $qb->andWhere('s.email LIKE :query OR s.firstName LIKE :query OR s.lastName LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($filters) {
            if (isset($filters['isActive'])) {
                $qb->andWhere('s.isActive = :isActive')
                   ->setParameter('isActive', $filters['isActive']);
            }
            
            if (isset($filters['campaignName'])) {
                $qb->andWhere('s.campaignName = :campaignName')
                   ->setParameter('campaignName', $filters['campaignName']);
            }
            
            if (isset($filters['dateFrom'])) {
                $qb->andWhere('s.subscribedAt >= :dateFrom')
                   ->setParameter('dateFrom', $filters['dateFrom']);
            }
            
            if (isset($filters['dateTo'])) {
                $qb->andWhere('s.subscribedAt <= :dateTo')
                   ->setParameter('dateTo', $filters['dateTo']);
            }
        }

        return $qb->orderBy('s.subscribedAt', 'DESC')
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    public function findByImportLog(ImportLog $importLog): array
    {
        return $this->createQueryBuilder('ns')
            ->where('ns.importLog = :importLog')
            ->andWhere('ns.isActive = :active')
            ->setParameter('importLog', $importLog)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function countRecentSubscribers(int $days = 7): int
    {
        $date = new \DateTime();
        $date->modify("-$days days");

        return $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.subscribedAt >= :date')
            ->andWhere('n.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findActiveSubscribersPaginated(int $page = 1, int $limit = 20): array
    {
        $query = $this->createQueryBuilder('s')
            ->select('s.id, s.email, s.firstName, s.lastName')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.email', 'ASC')
            ->getQuery();
        
        // Utilisez la pagination Doctrine
        $query->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        return $query->getResult();
    }

    public function countActiveSubscribers(): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getActiveSubscriberIds(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.id')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Trouver les abonnés actifs par leurs IDs (optimisé)
     */
    public function findActiveByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        
        return $this->createQueryBuilder('s')
            ->where('s.id IN (:ids)')
            ->andWhere('s.isActive = true')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }
}