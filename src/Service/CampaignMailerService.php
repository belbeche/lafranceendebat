<?php
// src/Service/CampaignMailerService.php

namespace App\Service;

use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * Service métier « CampaignMailerService ».
 */
class CampaignMailerService
{
    private MailerInterface $mailer;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    
    public function __construct(
        MailerInterface $mailer,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->mailer = $mailer;
        $this->em = $em;
        $this->logger = $logger;
    }
    
    public function sendCampaign(NewsletterCampaign $campaign, bool $sendTest = false, string $testEmail = null): array
    {
        if ($campaign->getStatus() !== 'pending' && !$sendTest) {
            return ['success' => false, 'error' => 'Campagne non prête à l\'envoi'];
        }
        
        try {
            // Mode test
            if ($sendTest && $testEmail) {
                return $this->sendTest($campaign, $testEmail);
            }
            
            // Mode production - envoyer à tous les abonnés
            $campaign->setStatus('sending');
            $campaign->setStartedAt(new \DateTimeImmutable());
            $this->em->flush();
            
            $subscribers = $campaign->getSubscribers();
            $sentCount = 0;
            $failedCount = 0;
            
            foreach ($subscribers as $subscriber) {
                if (!$subscriber->isActive()) {
                    continue;
                }
                
                try {
                    $email = (new Email())
                        ->from($campaign->getSenderEmail() ?: 'noreply@labouffonne.eu')
                        ->to($subscriber->getEmail())
                        ->subject($campaign->getSubject())
                        ->html($campaign->getContent())
                        ->text(strip_tags($campaign->getContent()));
                    
                    $this->mailer->send($email);
                    $sentCount++;
                    
                    // Mettre à jour le compteur toutes les 10 emails
                    if ($sentCount % 10 === 0) {
                        $campaign->setSentCount($sentCount);
                        $this->em->flush();
                    }
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    $this->logger->error('Erreur envoi email campagne', [
                        'campaign_id' => $campaign->getId(),
                        'subscriber_id' => $subscriber->getId(),
                        'email' => $subscriber->getEmail(),
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Finaliser la campagne
            $campaign->setStatus('completed');
            $campaign->setSentCount($sentCount);
            $campaign->setFailedCount($failedCount);
            $campaign->setCompletedAt(new \DateTimeImmutable());
            $this->em->flush();
            
            // Envoyer un email de rapport
            $this->sendReportEmail($campaign, $sentCount, $failedCount);
            
            return [
                'success' => true,
                'sent' => $sentCount,
                'failed' => $failedCount,
                'total' => count($subscribers)
            ];
            
        } catch (\Exception $e) {
            $campaign->setStatus('failed');
            $campaign->setError($e->getMessage());
            $this->em->flush();
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function sendTest(NewsletterCampaign $campaign, string $testEmail): array
    {
        try {
            $email = (new Email())
                ->from($campaign->getSenderEmail() ?: 'noreply@labouffonne.eu')
                ->to($testEmail)
                ->subject('[TEST] ' . $campaign->getSubject())
                ->html($campaign->getContent())
                ->text(strip_tags($campaign->getContent()));
            
            $this->mailer->send($email);
            
            return ['success' => true, 'message' => 'Email test envoyé à ' . $testEmail];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function sendReportEmail(NewsletterCampaign $campaign, int $sentCount, int $failedCount): void
    {
        try {
            $email = (new Email())
                ->from('noreply@labouffonne.eu')
                ->to('wbelbeche.s@gmail.com')
                ->subject('Rapport envoi campagne : ' . $campaign->getSubject())
                ->text(sprintf(
                    "Rapport d'envoi de campagne\n\n" .
                    "Campagne : %s\n" .
                    "Date d'envoi : %s\n" .
                    "Destinataires : %d\n" .
                    "Emails envoyés : %d\n" .
                    "Échecs : %d\n" .
                    "Taux de réussite : %.1f%%\n" .
                    "Statut : %s",
                    $campaign->getSubject(),
                    date('d/m/Y H:i:s'),
                    $campaign->getTotalRecipients(),
                    $sentCount,
                    $failedCount,
                    $campaign->getTotalRecipients() > 0 ? ($sentCount / $campaign->getTotalRecipients() * 100) : 0,
                    $campaign->getStatus()
                ));
            
            $this->mailer->send($email);
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email rapport', ['error' => $e->getMessage()]);
        }
    }
}