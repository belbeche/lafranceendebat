<?php
// src/Message/SendNewsletterMessage.php

namespace App\Message;

/**
 * Message asynchrone (Messenger) "SendNewsletterMessage".
 *
 * Traité en tâche de fond : un échec ne doit jamais bloquer la requête de l'utilisateur.
 */
class SendNewsletterMessage
{
    private int $campaignId;
    private int $userId;
    private array $options;

    public function __construct(int $campaignId, int $userId, array $options = [])
    {
        $this->campaignId = $campaignId;
        $this->userId = $userId;
        $this->options = $options;
    }

    public function getCampaignId(): int
    {
        return $this->campaignId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function isOptimized(): bool
    {
        return $this->options['optimized'] ?? false;
    }
}