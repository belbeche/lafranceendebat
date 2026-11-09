<?php

namespace App\MessageHandler;

use App\Message\SendNewsletterBatchMessage;
use App\Repository\NewsletterCampaignRepository;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Email\NewsletterEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;


/**
 * Message asynchrone (Messenger) "SendNewsletterBatchMessageHandler".
 *
 * Traité en tâche de fond : un échec ne doit jamais bloquer la requête de l'utilisateur.
 */
#[AsMessageHandler]
class SendNewsletterBatchMessageHandler
{
    public function __construct(
        private NewsletterCampaignRepository $campaignRepo,
        private NewsletterSubscriberRepository $subscriberRepo,
        private NewsletterEmailService $emailService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger
    ) {}

    public function __invoke(SendNewsletterBatchMessage $message): void
    {
        // 1. Récupérer la campagne
        $campaign = $this->campaignRepo->find($message->campaignId);
        
        // Sécurité : si la campagne a été annulée entre temps
        if (!$campaign || $campaign->getStatus() === 'cancelled') {
            return;
        }

        // 2. Récupérer SEULEMENT le lot de 500 abonnés
        $subscribers = $this->subscriberRepo->findBy(
            ['isActive' => true],
            ['id' => 'ASC'],
            $message->limit,
            $message->offset
        );

        // 3. Envoi
        foreach ($subscribers as $subscriber) {
            try {
            $this->emailService->sendCampaignEmail($campaign, $subscriber);
            } catch (\Exception $e) {
            $this->logger->error('Newsletter send failed', [
                'campaignId' => $message->campaignId,
                'subscriberId' => $subscriber->getId(),
                'error' => $e->getMessage()
            ]);
            }
        }

        // 4. Nettoyage Mémoire
        $this->em->flush();
        $this->em->clear();
    }
}