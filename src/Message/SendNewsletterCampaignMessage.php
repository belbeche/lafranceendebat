<?php

// src/Message/SendNewsletterCampaignMessage.php

namespace App\Message;

/**
 * Message asynchrone (Messenger) « SendNewsletterCampaignMessage ».
 *
 * Traité en tâche de fond : un échec ne doit jamais bloquer la requête de l'utilisateur.
 */
class SendNewsletterCampaignMessage
{
    private int $campaignId;

    public function __construct(int $campaignId)
    {
        $this->campaignId = $campaignId;
    }

    public function getCampaignId(): int
    {
        return $this->campaignId;
    }
}