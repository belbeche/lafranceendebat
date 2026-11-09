<?php

// src/MessageHandler/SendNewsletterMessageHandler.php
namespace App\MessageHandler;

use App\Message\SendNewsletterMessage;
use App\Service\Newsletter\CampaignSenderService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Gestionnaire de message Messenger "SendNewsletterMessageHandler".
 *
 * Traité en tâche de fond : un échec ne doit jamais bloquer la requête de l'utilisateur.
 */
class SendNewsletterMessageHandler implements MessageHandlerInterface
{
    public function __construct(
        private CampaignSenderService $campaignSender,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SendNewsletterMessage $message): void
    {
        try {
            $this->campaignSender->processCampaignBatch(
                $message->getCampaignId(),
                $message->getOptions()
            );
        } catch (\Exception $e) {
            $this->logger->error('Campaign batch failed: ' . $e->getMessage());
            
            // NE PAS relancer l'exception pour éviter les retry infinis
            // En prod, on veut que le message soit considéré comme traité même en cas d'erreur
        }
    }
}