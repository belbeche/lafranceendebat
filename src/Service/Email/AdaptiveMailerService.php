<?php

// src/Service/Email/AdaptiveMailerService.php

namespace App\Service\Email;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * Service métier « AdaptiveMailerService ».
 */
class AdaptiveMailerService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private bool $useMessenger;
    private DirectMailerService $directMailer;
    
    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger,
        DirectMailerService $directMailer,
        string $kernelEnvironment
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->directMailer = $directMailer;
        $this->useMessenger = $kernelEnvironment === 'prod';
    }
    
    public function send(Email $email): void
    {
        if ($this->useMessenger) {
            // En production : utiliser Messenger
            $this->mailer->send($email);
            $this->logger->debug('Email envoyé via Messenger');
        } else {
            // En développement : envoyer directement
            $this->directMailer->sendDirect($email);
            $this->logger->debug('Email envoyé directement');
        }
    }
}