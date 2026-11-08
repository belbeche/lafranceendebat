<?php

// src/DTO/CampaignSummary.php

namespace App\DTO;

/**
 * Objet de transfert de données « CampaignSummary ».
 */
class CampaignSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $subject,
        public readonly \DateTimeInterface $createdAt,
        public readonly int $totalRecipients
    ) {}
}