<?php

namespace App\Repository;

use App\Entity\ContentTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Dépôt Doctrine (requêtes d'une entité) « ContentTranslationRepository ».
 *
 * Entité gérée : ContentTranslation.
 */
class ContentTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentTranslation::class);
    }

    public function findPublished(string $type, int $id, string $locale): ?ContentTranslation
    {
        return $this->findOneBy(['type' => $type, 'refId' => $id, 'locale' => $locale, 'status' => ContentTranslation::PUBLISHED]);
    }

    /** @return string[] langues déjà traduites ou en attente */
    public function localesFor(string $type, int $id): array
    {
        return array_map(fn (ContentTranslation $t) => $t->getLocale(), $this->findBy(['type' => $type, 'refId' => $id]));
    }

    /** @return ContentTranslation[] */
    public function pending(int $limit = 100): array
    {
        return $this->findBy(['status' => ContentTranslation::PENDING], ['createdAt' => 'ASC'], $limit);
    }
}
