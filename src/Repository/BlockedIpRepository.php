<?php
// src/Repository/BlockedIpRepository.php

namespace App\Repository;

use App\Entity\BlockedIp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Dépôt Doctrine (requêtes d'une entité) "BlockedIpRepository".
 *
 * Entité gérée : BlockedIp.
 */
class BlockedIpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockedIp::class);
    }

    /**
     * Vérifie si une IP est bloquée
     */
    public function isBlocked(string $ipAddress): bool
    {
        $blocked = $this->createQueryBuilder('b')
            ->where('b.ipAddress = :ip')
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt > :now')
            ->setParameter('ip', $ipAddress)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
            
        return $blocked !== null;
    }

    /**
     * Bloque une IP
     */
    public function block(string $ipAddress, ?string $reason = null, ?int $hours = null, ?string $blockedBy = null): BlockedIp
    {
        $blocked = new BlockedIp();
        $blocked->setIpAddress($ipAddress);
        $blocked->setReason($reason);
        $blocked->setBlockedBy($blockedBy);
        
        if ($hours) {
            $expiresAt = new \DateTimeImmutable("+{$hours} hours");
            $blocked->setExpiresAt($expiresAt);
        }
        
        $this->getEntityManager()->persist($blocked);
        $this->getEntityManager()->flush();
        
        return $blocked;
    }

    /**
     * Débloque une IP
     */
    public function unblock(string $ipAddress): void
    {
        $this->createQueryBuilder('b')
            ->delete()
            ->where('b.ipAddress = :ip')
            ->setParameter('ip', $ipAddress)
            ->getQuery()
            ->execute();
    }

    /**
     * Nettoie les blocages expirés
     */
    public function cleanupExpired(): int
    {
        return $this->createQueryBuilder('b')
            ->delete()
            ->where('b.expiresAt IS NOT NULL')
            ->andWhere('b.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}