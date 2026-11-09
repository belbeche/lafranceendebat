<?php
// src/Repository/CampaignNoteRepository.php

namespace App\Repository;

use App\Entity\CampaignNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Dépôt Doctrine (requêtes d'une entité) "CampaignNoteRepository".
 *
 * Entité gérée : CampaignNote.
 */
class CampaignNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CampaignNote::class);
    }

    public function findAllGroupedByCampaign(): array
    {
        $notes = $this->findBy([], ['campaignId' => 'ASC', 'createdAt' => 'DESC']);
        
        $grouped = [];
        foreach ($notes as $note) {
            $campaignId = $note->getCampaignId();
            if (!isset($grouped[$campaignId])) {
                $grouped[$campaignId] = [
                    'campaignId' => $campaignId,
                    'campaignTitle' => $note->getCampaignTitle(),
                    'status' => $note->getStatus(),
                    'updatedAt' => $note->getUpdatedAt() ?? $note->getCreatedAt(),
                    'notes' => []
                ];
            }
            $grouped[$campaignId]['notes'][] = [
                'id' => $note->getId(),
                'content' => $note->getContent(),
                'author' => $note->getAuthor(),
                'createdAt' => $note->getCreatedAt()
            ];
        }
        
        return array_values($grouped);
    }

    public function findByCampaignId(int $campaignId): array
    {
        return $this->findBy(
            ['campaignId' => $campaignId],
            ['createdAt' => 'DESC']
        );
    }
}