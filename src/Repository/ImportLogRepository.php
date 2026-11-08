<?php

// src/Repository/ImportLogRepository.php
namespace App\Repository;

use App\Entity\ImportLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportLog>
 */
class ImportLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportLog::class);
    }

    public function findRecentImports(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.importedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getImportStats(): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select(
                'COUNT(i.id) as total_imports',
                'SUM(i.recordsImported) as total_imported_records',
                'SUM(i.recordsFailed) as total_failed_records'
            );

        return $qb->getQuery()->getSingleResult();
    }

    public function findByEntityType(string $entityType): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.entityType = :entityType')
            ->setParameter('entityType', $entityType)
            ->orderBy('i.importedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findWithNewsletterSubscribers(string $entityType = 'NewsletterSubscriber'): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.newsletterSubscribers', 's')
            ->addSelect('s')
            ->where('i.entityType = :entityType')
            ->setParameter('entityType', $entityType)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}