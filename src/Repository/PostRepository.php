<?php
// src/Repository/PostRepository.php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    public function findPublishedVideos(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->andWhere('p.type = :type')
            ->setParameter('published', true)
            ->setParameter('type', 'video')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublishedImages(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->andWhere('p.type = :type')
            ->setParameter('published', true)
            ->setParameter('type', 'image')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublishedPosts(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->andWhere('p.type = :type')
            ->setParameter('published', true)
            ->setParameter('type', $type)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRelatedPosts(Post $post, int $limit = 3): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->andWhere('p.id != :currentId')
            ->andWhere('p.type = :type')
            ->setParameter('published', true)
            ->setParameter('currentId', $post->getId())
            ->setParameter('type', $post->getType())
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLatestPosts(int $limit = 6): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPostsWithForms(): array
    {
        $posts = $this->findBy([], ['createdAt' => 'DESC']);
        $postsWithForms = [];
        
        foreach ($posts as $post) {
            $postsWithForms[] = [
                'post' => $post,
                'commentForm' => null // Vous pouvez créer le formulaire ici si nécessaire
            ];
        }
        
        return $postsWithForms;
    }

    public function findAllPaginated(int $page = 1, int $limit = 10): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $queryBuilder->getQuery()->getResult();
    }

    public function findPublishedPaginated(int $page = 1, int $limit = 10): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->where('p.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $queryBuilder->getQuery()->getResult();
    }
}