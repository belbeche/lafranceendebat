<?php

namespace App\Service\Newsletter;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

class SimpleCampaignSender
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    public function sendCampaign(int $campaignId): array
    {
        // Add your campaign sending logic here
        $this->logger->info("Sending campaign #{$campaignId}");
        return ['success' => true];
    }
}