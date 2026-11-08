<?php

namespace App\Repository;

use App\Entity\ActorContact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ActorContact> */
class ActorContactRepository extends ServiceEntityRepository
{
    /** Une personne ayant consenti recoit au plus un message par semaine. */
    public const MIN_DAYS_BETWEEN_CONTACTS = 7;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActorContact::class);
    }

    private function localized(QueryBuilder $qb, ?string $locale): QueryBuilder
    {
        return $locale ? $qb->andWhere('a.locale = :loc')->setParameter('loc', $locale) : $qb;
    }

    /** invitation : jamais invite, jamais consenti. acteurs : a consenti, au plus un message par semaine. */
    private function eligible(bool $invitation): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')->andWhere('a.status = :s')->setParameter('s', ActorContact::ACTIVE);
        if ($invitation) {
            return $qb->andWhere('a.consentAt IS NULL')->andWhere('a.invitedAt IS NULL');
        }

        return $qb->andWhere('a.consentAt IS NOT NULL')
            ->andWhere('a.lastContactedAt IS NULL OR a.lastContactedAt < :d')
            ->setParameter('d', new \DateTimeImmutable('-' . self::MIN_DAYS_BETWEEN_CONTACTS . ' days'));
    }

    /** Domaines de messagerie grand public : ne désignent jamais une organisation. */
    private const GENERIC_DOMAINS = ['gmail.com', 'hotmail.com', 'hotmail.fr', 'yahoo.fr', 'yahoo.com', 'orange.fr', 'wanadoo.fr', 'free.fr', 'sfr.fr', 'neuf.fr', 'live.fr', 'live.com', 'outlook.com', 'outlook.fr', 'icloud.com', 'me.com', 'aol.com', 'laposte.net', 'bbox.fr', 'proton.me', 'protonmail.com', 'msn.com'];

    /**
     * Une organisation (même nom et même code postal, ou même nom de domaine professionnel) ne reçoit qu'un seul message :
     * invitation = jamais si l'organisation a déjà été invitée ou contactée ; autres lettres = pas avant le délai minimal.
     */
    public function organisationAlreadyReached(ActorContact $a, bool $invitation): bool
    {
        $qb = $this->createQueryBuilder('o')->select('COUNT(o.id)')->andWhere('o.id != :me')->setParameter('me', (int) $a->getId());
        $same = $qb->expr()->orX('(LOWER(o.organisation) = :org AND COALESCE(o.postalCode, \'\') = :pc)');
        $qb->setParameter('org', mb_strtolower(trim($a->getOrganisation())))->setParameter('pc', (string) $a->getPostalCode());
        $domain = strtolower((string) substr(strrchr($a->getEmail(), '@') ?: '', 1));
        if ('' !== $domain && !in_array($domain, self::GENERIC_DOMAINS, true)) {
            $same->add('o.email LIKE :dom');
            $qb->setParameter('dom', '%@' . addcslashes($domain, '%_'));
        }
        $qb->andWhere($same);
        if ($invitation) {
            $qb->andWhere('o.invitedAt IS NOT NULL OR o.lastContactedAt IS NOT NULL');
        } else {
            $qb->andWhere('o.lastContactedAt >= :since')->setParameter('since', new \DateTimeImmutable('-' . self::MIN_DAYS_BETWEEN_CONTACTS . ' days'));
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /** Ciblage : categorie et departement (prefixe du code postal ; 2A/2B = 20). */
    private function target(QueryBuilder $qb, ?string $category, ?string $dept): QueryBuilder
    {
        if ($category) {
            $qb->andWhere('a.category = :cat')->setParameter('cat', $category);
        }
        if ($dept) {
            $qb->andWhere('a.postalCode LIKE :dep')->setParameter('dep', (in_array($dept, ['2A', '2B'], true) ? '20' : $dept) . '%');
        }

        return $qb;
    }

    public function countEligible(bool $invitation = false, ?string $locale = null, ?string $category = null, ?string $dept = null): int
    {
        return (int) $this->target($this->localized($this->eligible($invitation), $locale), $category, $dept)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
    }

    /** @return ActorContact[] */
    public function nextRecipients(int $afterId, int $limit, bool $invitation = false, ?string $locale = null, ?string $category = null, ?string $dept = null): array
    {
        return $this->target($this->localized($this->eligible($invitation), $locale), $category, $dept)->andWhere('a.id > :c')->setParameter('c', $afterId)->orderBy('a.id', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }

    public function countConsented(): int
    {
        return (int) $this->createQueryBuilder('a')->select('COUNT(a.id)')->andWhere('a.consentAt IS NOT NULL')->andWhere('a.status = :s')->setParameter('s', ActorContact::ACTIVE)->getQuery()->getSingleScalarResult();
    }

    /** @return array<string,int> nombre de contacts par statut */
    public function countsByStatus(): array
    {
        $out = [];
        foreach ($this->createQueryBuilder('a')->select('a.status AS s, COUNT(a.id) AS n')->groupBy('a.status')->getQuery()->getArrayResult() as $r) {
            $out[$r['s']] = (int) $r['n'];
        }

        return $out;
    }
}
