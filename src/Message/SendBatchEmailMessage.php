<?php

namespace App\Message;

/**
 * Message asynchrone (Messenger) "SendBatchEmailMessage".
 *
 * Traité en tâche de fond : un échec ne doit jamais bloquer la requête de l'utilisateur.
 */
class SendBatchEmailMessage
{
    private int $campaignId;
    private array $subscriberIds;
    private int $batchNumber;

    public function __construct(int $campaignId, array $subscriberIds, int $batchNumber = 0)
    {
        $this->campaignId = $campaignId;
        $this->subscriberIds = $subscriberIds;
        $this->batchNumber = $batchNumber;
    }

    public function getCampaignId(): int
    {
        return $this->campaignId;
    }

    public function getSubscriberIds(): array
    {
        return $this->subscriberIds;
    }

    public function getBatchNumber(): int
    {
        return $this->batchNumber;
    }
}