<?php

namespace App\Service;

use App\Entity\ModerationLock;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verrou de modération : une seule personne décide d'un contenu à la fois.
 *
 * Quand un modérateur ouvre un contenu, il le « prend » pour un temps court ; les autres voient qui s'en occupe
 * et ne peuvent pas décider en parallèle. Pour verrouiller un nouveau type de contenu, ajouter sa route de décision
 * à ROUTES (type fixe, ou null si la route porte un paramètre « type »). Les motifs de refus proposés sont dans REASONS.
 */
class ModerationLocks
{
    /** Routes de decision : nom de route => [type de contenu (valeur fixe ou null = parametre "type")]. */
    public const ROUTES = [
        'admin_moderation_decide' => null,
        'admin_moderation_claim' => 'revendication',
        'admin_moderation_tribune' => 'tribune',
        'admin_moderation_missionpost' => 'annonce',
        'admin_moderation_inspiration' => 'contributeur',
        'admin_moderation_dossier' => 'dossier',
    ];
    public const REASONS = [
        'Attaque personnelle ou insulte',
        'Source absente ou non vérifiable',
        'Hors sujet du débat',
        'Coordonnées personnelles dans le texte',
        'Appel à la haine ou à la violence',
        'Contenu déjà publié (doublon)',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function active(string $kind, int $id): ?ModerationLock
    {
        $rows = $this->em->getRepository(ModerationLock::class)->findBy(['kind' => $kind, 'targetId' => $id, 'decidedAt' => null], ['id' => 'DESC'], 1);

        return ($rows[0] ?? null)?->isActive() ? $rows[0] : null;
    }

    /** Verrou actif tenu par quelqu'un d'autre que $me, sinon null. */
    public function heldByOther(string $kind, int $id, User $me): ?ModerationLock
    {
        $l = $this->active($kind, $id);

        return $l && $l->getUser()->getId() !== $me->getId() ? $l : null;
    }

    public function take(string $kind, int $id, User $me): ?ModerationLock
    {
        if ($this->heldByOther($kind, $id, $me)) {
            return null;
        }
        if ($mine = $this->active($kind, $id)) {
            return $mine;
        }
        $l = new ModerationLock($kind, $id, $me);
        $this->em->persist($l);
        $this->em->flush();

        return $l;
    }

    public function recordDecision(string $kind, int $id, User $me): void
    {
        $l = $this->active($kind, $id);
        if (!$l || $l->getUser()->getId() !== $me->getId()) {
            $l = new ModerationLock($kind, $id, $me);
            $this->em->persist($l);
        }
        $l->decide();
        $this->em->flush();
    }

    /** @return array<string,ModerationLock> verrous actifs indexes par "kind:id" */
    public function activeMap(): array
    {
        $out = [];
        foreach ($this->em->getRepository(ModerationLock::class)->createQueryBuilder('l')->andWhere('l.decidedAt IS NULL')->andWhere('l.expiresAt > :n')->setParameter('n', new \DateTimeImmutable())->orderBy('l.id', 'ASC')->getQuery()->getResult() as $l) {
            $out[$l->getKind() . ':' . $l->getTargetId()] = $l;
        }

        return $out;
    }

    /** @return list<array{name:string,decided:int,held:int}> charge de l'equipe aujourd'hui */
    public function workload(): array
    {
        $today = new \DateTimeImmutable('today');
        $by = [];
        foreach ($this->em->getRepository(ModerationLock::class)->createQueryBuilder('l')->andWhere('l.takenAt >= :t')->setParameter('t', $today)->getQuery()->getResult() as $l) {
            $n = $l->getUser()->getPublicName();
            $by[$n] ??= ['name' => $n, 'decided' => 0, 'held' => 0];
            if ($l->getDecidedAt()) {
                ++$by[$n]['decided'];
            } elseif ($l->isActive()) {
                ++$by[$n]['held'];
            }
        }
        usort($by, static fn ($a, $b) => $b['decided'] <=> $a['decided']);

        return array_values($by);
    }
}
