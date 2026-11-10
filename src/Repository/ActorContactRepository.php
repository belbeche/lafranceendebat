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
            // Une invitation ne part que pour une fiche relue et validee par l'equipe.
            return $qb->andWhere('a.consentAt IS NULL')->andWhere('a.invitedAt IS NULL')->andWhere('a.validatedAt IS NOT NULL');
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
        // Une même préfecture ou mairie peut avoir plusieurs adresses e-mail (domaines différents) : on rapproche aussi
        // par site web, par téléphone et par adresse postale (rue + code postal) identiques.
        $web = rtrim(mb_strtolower(trim((string) $a->getWebsite())), '/');
        if ('' !== $web) {
            $same->add('LOWER(o.website) = :web OR LOWER(o.website) = :web2');
            $qb->setParameter('web', $web)->setParameter('web2', $web . '/');
        }
        $tel = preg_replace('/\D+/', '', (string) $a->getPhone()) ?? '';
        if (strlen($tel) >= 9) {
            // DQL n'a pas de REPLACE : on cherche les écritures usuelles du même numéro (brut, collé, espaces, points).
            $pairs = str_split(substr($tel, -10), 2);
            $variants = array_unique([trim((string) $a->getPhone()), $tel, implode(' ', $pairs), implode('.', $pairs)]);
            $same->add('o.phone IN (:tels)');
            $qb->setParameter('tels', $variants);
        }
        $addr = mb_strtolower(trim((string) $a->getAddress()));
        if (strlen($addr) >= 8 && '' !== (string) $a->getPostalCode()) {
            $same->add('(LOWER(o.address) = :addr AND o.postalCode = :pc2)');
            $qb->setParameter('addr', $addr)->setParameter('pc2', (string) $a->getPostalCode());
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

    /**
     * Explique pourquoi une audience est vide : nombre de contacts restant après chaque filtre, dans l'ordre où ils s'appliquent.
     * Sert uniquement à l'écran d'administration d'une lettre (aucune donnée personnelle n'est renvoyée).
     *
     * @return list<array{label:string,count:int}>
     */
    public function diagnose(bool $invitation, ?string $locale, ?string $category, ?string $dept): array
    {
        $count = static fn (QueryBuilder $qb): int => (int) $qb->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $steps = [['Contacts en base', $count($this->createQueryBuilder('a'))]];
        $active = $this->createQueryBuilder('a')->andWhere('a.status = :s')->setParameter('s', ActorContact::ACTIVE);
        $steps[] = ['Actifs (ni désinscrits, ni rebonds, ni plaintes)', $count(clone $active)];
        if ($invitation) {
            $steps[] = ['Fiches validées par l\'équipe', $count((clone $active)->andWhere('a.validatedAt IS NOT NULL'))];
        }
        $eligible = $this->eligible($invitation);
        $steps[] = [$invitation ? 'Validés, jamais invités et sans consentement' : 'Ayant consenti et non contactés depuis ' . self::MIN_DAYS_BETWEEN_CONTACTS . ' jours', $count(clone $eligible)];
        if ($locale) {
            $eligible = $this->localized($eligible, $locale);
            $steps[] = ['Langue « ' . $locale . ' »', $count(clone $eligible)];
        }
        if ($category) {
            $eligible = $this->target($eligible, $category, null);
            $steps[] = ['Catégorie « ' . $category . ' »', $count(clone $eligible)];
        }
        if ($dept) {
            $eligible = $this->target($eligible, null, $dept);
            $steps[] = ['Département ' . $dept . ' (code postal renseigné)', $count(clone $eligible)];
        }

        return array_map(static fn (array $r): array => ['label' => $r[0], 'count' => $r[1]], $steps);
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

    /**
     * Filtre d'administration (page "Acteurs publics") : tous les critères sont facultatifs.
     * q cherche dans l'organisation, la ville, l'e-mail et la fonction. dept = préfixe du code postal (2A/2B = 20).
     *
     * @param array<string,string> $f clés : q, category, dept, city, status, invited (oui|non), consent (oui|non), validated (oui|non), phone, website, address (oui|non)
     */
    public function filtered(array $f): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a');
        if (($f['q'] ?? '') !== '') {
            $qb->andWhere('a.organisation LIKE :q OR a.city LIKE :q OR a.email LIKE :q OR a.role LIKE :q')->setParameter('q', '%' . addcslashes(mb_substr($f['q'], 0, 80), '%_') . '%');
        }
        if (($f['category'] ?? '') !== '') {
            $qb->andWhere('a.category = :cat')->setParameter('cat', $f['category']);
        }
        if (($f['dept'] ?? '') !== '') {
            $d = strtoupper($f['dept']);
            $qb->andWhere('a.postalCode LIKE :dep')->setParameter('dep', (in_array($d, ['2A', '2B'], true) ? '20' : $d) . '%');
        }
        if (($f['city'] ?? '') !== '') {
            $qb->andWhere('a.city = :city')->setParameter('city', $f['city']);
        }
        if (($f['status'] ?? '') !== '') {
            $qb->andWhere('a.status = :st')->setParameter('st', $f['status']);
        }
        foreach (['invited' => 'a.invitedAt', 'consent' => 'a.consentAt', 'validated' => 'a.validatedAt'] as $k => $col) {
            if ('oui' === ($f[$k] ?? '')) {
                $qb->andWhere("$col IS NOT NULL");
            } elseif ('non' === ($f[$k] ?? '')) {
                $qb->andWhere("$col IS NULL");
            }
        }
        foreach (['phone' => 'a.phone', 'website' => 'a.website', 'address' => 'a.address'] as $k => $col) {
            if ('oui' === ($f[$k] ?? '')) {
                $qb->andWhere("$col IS NOT NULL AND $col != ''");
            } elseif ('non' === ($f[$k] ?? '')) {
                $qb->andWhere("$col IS NULL OR $col = ''");
            }
        }

        return $qb;
    }

    /** Chiffres clés de la base entière. @return array<string,int> */
    public function overview(): array
    {
        $r = $this->createQueryBuilder('a')->select(
            'COUNT(a.id) AS total',
            "SUM(CASE WHEN a.status = 'actif' THEN 1 ELSE 0 END) AS actifs",
            'SUM(CASE WHEN a.invitedAt IS NOT NULL THEN 1 ELSE 0 END) AS invites',
            'SUM(CASE WHEN a.validatedAt IS NOT NULL THEN 1 ELSE 0 END) AS valides',
            "SUM(CASE WHEN a.status = 'actif' AND a.validatedAt IS NULL AND a.invitedAt IS NULL AND a.consentAt IS NULL THEN 1 ELSE 0 END) AS a_valider",
            'SUM(CASE WHEN a.consentAt IS NOT NULL THEN 1 ELSE 0 END) AS accords',
            "SUM(CASE WHEN a.phone IS NOT NULL AND a.phone != '' THEN 1 ELSE 0 END) AS tel",
            "SUM(CASE WHEN a.website IS NOT NULL AND a.website != '' THEN 1 ELSE 0 END) AS site",
            "SUM(CASE WHEN a.address IS NOT NULL AND a.address != '' THEN 1 ELSE 0 END) AS adresse",
            "SUM(CASE WHEN a.postalCode IS NULL OR a.postalCode = '' THEN 1 ELSE 0 END) AS sans_cp",
        )->getQuery()->getSingleResult();

        return array_map('intval', $r);
    }

    /** Répartition par catégorie : total et nombre jamais invités (envoi d'invitation possible). @return list<array{category:string,total:int,todo:int}> */
    public function byCategory(): array
    {
        $rows = $this->createQueryBuilder('a')->select('a.category AS c', 'COUNT(a.id) AS n', "SUM(CASE WHEN a.status = 'actif' AND a.invitedAt IS NULL AND a.consentAt IS NULL AND a.validatedAt IS NOT NULL THEN 1 ELSE 0 END) AS todo")->groupBy('a.category')->orderBy('n', 'DESC')->getQuery()->getArrayResult();

        return array_map(static fn ($r) => ['category' => $r['c'], 'total' => (int) $r['n'], 'todo' => (int) $r['todo']], $rows);
    }

    /** Répartition par département (2 premiers chiffres du code postal ; 97x = 3). @return list<array{dept:string,total:int,todo:int}> */
    public function byDepartment(): array
    {
        $rows = $this->createQueryBuilder('a')->select('a.postalCode AS cp', 'COUNT(a.id) AS n', "SUM(CASE WHEN a.status = 'actif' AND a.invitedAt IS NULL AND a.consentAt IS NULL AND a.validatedAt IS NOT NULL THEN 1 ELSE 0 END) AS todo")
            ->andWhere('a.postalCode IS NOT NULL')->andWhere("a.postalCode != ''")->groupBy('a.postalCode')->getQuery()->getArrayResult();
        $out = [];
        foreach ($rows as $r) {
            $cp = (string) $r['cp'];
            $d = str_starts_with($cp, '97') || str_starts_with($cp, '98') ? substr($cp, 0, 3) : substr($cp, 0, 2);
            $out[$d] ??= ['dept' => $d, 'total' => 0, 'todo' => 0];
            $out[$d]['total'] += (int) $r['n'];
            $out[$d]['todo'] += (int) $r['todo'];
        }
        ksort($out);

        return array_values($out);
    }

    /** Communes (villes) d'un département, pour le filtre. @return list<string> */
    public function citiesOf(string $dept): array
    {
        $qb = $this->createQueryBuilder('a')->select('DISTINCT a.city AS c')->andWhere('a.city IS NOT NULL')->andWhere("a.city != ''")->orderBy('a.city', 'ASC')->setMaxResults(1500);
        $d = strtoupper($dept);
        $qb->andWhere('a.postalCode LIKE :dep')->setParameter('dep', (in_array($d, ['2A', '2B'], true) ? '20' : $d) . '%');

        return array_column($qb->getQuery()->getArrayResult(), 'c');
    }
}
