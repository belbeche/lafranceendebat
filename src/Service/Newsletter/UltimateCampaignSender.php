<?php
// src/Service/Newsletter/UltimateCampaignSender.php
namespace App\Service\Newsletter;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class UltimateCampaignSender
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private $directTransport;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->logger = $logger;
        
        // Créer un transport direct
        $this->directTransport = \Symfony\Component\Mailer\Transport::fromDsn($_ENV['MAILER_DSN']);
    }
    
    public function sendCampaign(int $campaignId): array
    {
        $this->logger->info("=== ULTIMATE SENDER START - Campaign {$campaignId} ===");
        
        $campaign = $this->entityManager->getRepository(\App\Entity\NewsletterCampaign::class)->find($campaignId);
        
        if (!$campaign) {
            return ['success' => false, 'error' => 'Campaign not found'];
        }
        
        // Réinitialiser si bloqué
        $campaign->setStatus('processing');
        $campaign->setStartedAt(new \DateTimeImmutable());
        $campaign->setSentCount(0);
        $campaign->setFailedCount(0);
        $campaign->setError(null);
        $this->entityManager->flush();
        
        $sent = 0;
        $failed = 0;
        
        foreach ($campaign->getSubscribers() as $subscriber) {
            if (!$subscriber->isActive()) {
                $failed++;
                continue;
            }
            
            try {
                // TEST ULTIME - Email très simple
                $email = (new Email())
                    ->from('newsletter@labouffonne.eu')
                    ->to($subscriber->getEmail())
                    ->subject('Newsletter: ' . $campaign->getSubject())
                    ->text('Bonjour ' . ($subscriber->getFirstName() ?: '') . ",\n\n" . 
                           strip_tags($campaign->getContent()) . "\n\n--\nLa France en Débat")
                    ->html($this->createSimpleHtml($campaign, $subscriber));
                
                // ENVOI DIRECT ULTIME
                $this->directTransport->send($email);
                
                $sent++;
                $this->logger->info("✓ SENT to: " . $subscriber->getEmail());
                
            } catch (\Exception $e) {
                $failed++;
                $this->logger->error("✗ FAILED to: " . $subscriber->getEmail() . " - " . $e->getMessage());
            }
            
            // Mise à jour
            $campaign->setSentCount($sent);
            $campaign->setFailedCount($failed);
            $this->entityManager->flush();
            
            usleep(50000);
        }
        
        // Final
        $campaign->setStatus($sent > 0 ? 'completed' : 'failed');
        $campaign->setCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        $result = "{$sent} sent, {$failed} failed";
        $this->logger->info("=== ULTIMATE SENDER END - {$result} ===");
        
        return [
            'success' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed,
            'message' => $result
        ];
    }
    
    private function createSimpleHtml($campaign, $subscriber): string
    {
        $content = strip_tags($campaign->getContent(), '<p><br><strong><em><a><h1><h2><h3><h4><ul><li>');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>{$campaign->getSubject()}</h1>
                <div>{$content}</div>
                <div class='footer'>
                    <p>Envoyé par La France en Débat</p>
                    <p><a href='#'>Se désabonner</a></p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}