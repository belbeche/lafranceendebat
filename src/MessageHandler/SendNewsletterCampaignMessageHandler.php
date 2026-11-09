<?php

namespace App\MessageHandler;

use App\Message\SendNewsletterCampaignMessage;
use App\Entity\NewsletterCampaign;
use App\Service\Email\NewsletterEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Gestionnaire de message Messenger "SendNewsletterCampaignMessageHandler".
 *
 * Traité en tâche de fond : un échec ne doit jamais bloquer la requête de l'utilisateur.
 */
#[AsMessageHandler]
class SendNewsletterCampaignMessageHandler
{
    private EntityManagerInterface $entityManager;
    private NewsletterEmailService $emailService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        NewsletterEmailService $emailService,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
        $this->logger = $logger;
    }

    public function __invoke(SendNewsletterCampaignMessage $message): void
    {
        $campaignId = $message->getCampaignId();
        
        $this->logger->info("=== DÉMARRAGE HANDLER CAMPAGNE #{$campaignId} ===");
        
        try {
            // Récupérer la campagne
            $campaign = $this->entityManager->getRepository(NewsletterCampaign::class)->find($campaignId);
            
            if (!$campaign) {
                $this->logger->error("Campagne #{$campaignId} non trouvée");
                return;
            }
            
            // Vérifier le statut
            if ($campaign->getStatus() !== 'processing') {
                $this->logger->warning("Campagne #{$campaignId} pas en 'processing' (statut: {$campaign->getStatus()})");
                return;
            }
            
            $total = $campaign->getTotalRecipients();
            $this->logger->info("Destinataires: {$total}");
            
            $sent = 0;
            $failed = 0;
            
            // Récupérer les abonnés
            $subscribers = $campaign->getSubscribers();
            
            foreach ($subscribers as $index => $subscriber) {
                $current = $index + 1;
                
                if (!$subscriber->isActive()) {
                    $failed++;
                    continue;
                }
                
                try {
                    $result = $this->emailService->sendCampaignEmail($campaign, $subscriber);
                    
                    if ($result) {
                        $sent++;
                        $this->logger->info("[{$current}/{$total}] ✓ {$subscriber->getEmail()}");
                    } else {
                        $failed++;
                        $this->logger->warning("[{$current}/{$total}] ✗ {$subscriber->getEmail()}");
                    }
                    
                    // Mise à jour périodique
                    if (($sent + $failed) % 10 === 0 || $current === $total) {
                        $campaign->setSentCount($sent);
                        $campaign->setFailedCount($failed);
                        $campaign->setLastUpdate(new \DateTimeImmutable());
                        $this->entityManager->flush();
                    }
                    
                    // Petite pause
                    usleep(50000);
                    
                } catch (\Exception $e) {
                    $failed++;
                    $this->logger->error("[{$current}/{$total}] Erreur {$subscriber->getEmail()}: " . $e->getMessage());
                }
            }
            
            // Finalisation
            if ($sent > 0) {
                $campaign->setStatus('completed');
                $campaign->setCompletedAt(new \DateTimeImmutable());
                $this->logger->info("Campagne #{$campaignId} terminée: {$sent} envoyés, {$failed} échecs");
            } else {
                $campaign->setStatus('failed');
                $campaign->setError('Aucun email envoyé');
                $this->logger->error("Campagne #{$campaignId} échouée: aucun email envoyé");
            }
            
            $campaign->setSentCount($sent);
            $campaign->setFailedCount($failed);
            $this->entityManager->flush();
            
            $this->logger->info("=== FIN HANDLER CAMPAGNE #{$campaignId} ===");
            
        } catch (\Exception $e) {
            $this->logger->error("ERREUR GLOBALE CAMPAGNE #{$campaignId}: " . $e->getMessage());
            
            // Marquer comme échouée
            try {
                $campaign = $this->entityManager->getRepository(NewsletterCampaign::class)->find($campaignId);
                if ($campaign) {
                    $campaign->setStatus('failed');
                    $campaign->setError($e->getMessage());
                    $this->entityManager->flush();
                }
            } catch (\Exception $flushError) {
                $this->logger->error("Erreur mise à jour statut: " . $flushError->getMessage());
            }
        }
    }
}