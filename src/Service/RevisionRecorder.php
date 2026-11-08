<?php

namespace App\Service;

use App\Entity\ContentRevision;
use Doctrine\ORM\EntityManagerInterface;

/** Conserve l'ancienne version d'un contenu modifie, avec le meme delai de grace de 5 minutes que la mention « modifie ». */
class RevisionRecorder
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @param array<string,string> $old valeurs avant modification */
    public function record(string $type, int $refId, array $old, \DateTimeImmutable $createdAt, bool $byModerator, array $new = []): void
    {
        if ($createdAt >= new \DateTimeImmutable('-5 minutes')) {
            return;
        }
        if ($new && array_intersect_key($old, $new) == array_intersect_key($new, $old)) {
            return; // aucune difference reelle
        }
        $this->em->persist(new ContentRevision($type, $refId, array_map(static fn ($v) => (string) $v, $old), $byModerator));
    }

    /** @return list<ContentRevision> */
    public function history(string $type, int $refId): array
    {
        return $this->em->getRepository(ContentRevision::class)->findBy(['type' => $type, 'refId' => $refId], ['createdAt' => 'DESC'], 50);
    }

    public function count(string $type, int $refId): int
    {
        return (int) $this->em->getRepository(ContentRevision::class)->count(['type' => $type, 'refId' => $refId]);
    }
}
