<?php

// src/Message/SendNewsletterBatchMessage.php
namespace App\Message;

/**
 * Message asynchrone (Messenger) « SendNewsletterBatchMessage ».
 *
 * Traité en tâche de fond : un échec ne doit jamais bloquer la requête de l'utilisateur.
 */
class SendNewsletterBatchMessage
{
    public function __construct(
        public int $campaignId,
        public int $offset,
        public int $limit
    ) {}
}