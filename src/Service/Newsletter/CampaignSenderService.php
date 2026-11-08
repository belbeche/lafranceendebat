<?php
// src/Service/Newsletter/CampaignSenderService.php

namespace App\Service\Newsletter;

use App\Entity\NewsletterCampaign;
use App\Entity\User;
use App\Message\SendNewsletterCampaignMessage;
use App\MessageHandler\SendNewsletterCampaignMessageHandler;
use App\Service\Email\NewsletterEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service métier « CampaignSenderService ».
 */
class CampaignSenderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private NewsletterEmailService $emailService  // AJOUTER CETTE LIGNE
    ) {
    }

    /**
     * Démarre une campagne en arrière-plan (asynchrone)
     */
    public function startCampaignAsync(NewsletterCampaign $campaign, ?User $user = null): void
    {
        $this->logger->info("=== DÉMARRAGE ASYNC CAMPAGNE #{$campaign->getId()} ===");
        
        // Vérifier le statut
        if ($campaign->getStatus() !== 'pending' && $campaign->getStatus() !== 'paused') {
            $this->logger->warning("Impossible de démarrer: statut {$campaign->getStatus()}");
            return;
        }
        
        // Mettre à jour le statut
        $campaign->setStatus('processing');
        $campaign->setStartedAt(new \DateTimeImmutable());
        $campaign->setStartedBy($user);
        $campaign->setLastUpdate(new \DateTimeImmutable());
        
        $this->entityManager->flush();
        
        $this->logger->info("Statut passé à 'processing'");
        $this->logger->info("Envoi du message dans la file d'attente...");
        
        // Envoyer le message dans la file d'attente
        $message = new SendNewsletterCampaignMessage($campaign->getId());
        $this->bus->dispatch($message);
        
        $this->logger->info("✅ Message envoyé dans la file d'attente pour la campagne #{$campaign->getId()}");
    }

    public function sendCampaignSync(NewsletterCampaign $campaign, ?User $user = null): void
    {
        // LOG IMMÉDIAT
        error_log("=== sendCampaignSync CALLED for campaign #{$campaign->getId()} ===");
        $this->logger->error("!!! sendCampaignSync CALLED !!!");
        
        $this->logger->info("=== DÉMARRAGE SYNC CAMPAGNE #{$campaign->getId()} ===");
        
        if ($campaign->getStatus() !== 'pending') {
            $this->logger->warning("Impossible de démarrer: statut {$campaign->getStatus()}");
            return;
        }
        
        $campaign->setStatus('processing');
        $campaign->setStartedAt(new \DateTimeImmutable());
        $campaign->setStartedBy($user);
        $campaign->setLastUpdate(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        $subscribers = $campaign->getSubscribers();
        $total = $subscribers->count();
        $this->logger->info("{$total} destinataires à traiter");
        
        $sent = 0;
        $failed = 0;
        
        foreach ($subscribers as $subscriber) {
            $this->logger->info("Envoi à: " . $subscriber->getEmail());
            
            try {
                $result = $this->emailService->sendCampaignEmail($campaign, $subscriber);
                
                if ($result) {
                    $sent++;
                    $this->logger->info("✓ Succès: {$subscriber->getEmail()}");
                } else {
                    $failed++;
                    $this->logger->warning("✗ Échec: {$subscriber->getEmail()}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->logger->error("Erreur {$subscriber->getEmail()}: " . $e->getMessage());
                $this->logger->error($e->getTraceAsString());
            }
        }
        
        $campaign->setSentCount($sent);
        $campaign->setFailedCount($failed);
        $campaign->setStatus($sent > 0 ? 'completed' : 'failed');
        $campaign->setCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        
        $this->logger->info("=== FIN SYNC: {$sent} envoyés, {$failed} échecs ===");
    }

    /**
     * Annule une campagne en cours
     */
    public function cancelCampaign(NewsletterCampaign $campaign): void
    {
        $this->logger->info("Annulation de la campagne #{$campaign->getId()}");
        
        $campaign->setStatus('cancelled');
        $campaign->setCompletedAt(new \DateTimeImmutable());
        $campaign->setLastUpdate(new \DateTimeImmutable());
        
        $this->entityManager->flush();
        
        $this->logger->info("✅ Campagne #{$campaign->getId()} annulée");
    }

    /**
     * Met en pause une campagne
     */
    public function pauseCampaign(NewsletterCampaign $campaign): void
    {
        $this->logger->info("Pause de la campagne #{$campaign->getId()}");
        
        $campaign->setStatus('paused');
        $campaign->setLastUpdate(new \DateTimeImmutable());
        
        $this->entityManager->flush();
        
        $this->logger->info("✅ Campagne #{$campaign->getId()} en pause");
    }

    /**
     * Reprend une campagne en pause
     */
    public function resumeCampaign(NewsletterCampaign $campaign, ?User $user = null): void
    {
        $this->logger->info("Reprise de la campagne #{$campaign->getId()}");
        
        $campaign->setStatus('processing');
        $campaign->setStartedBy($user);
        $campaign->setLastUpdate(new \DateTimeImmutable());
        
        $this->entityManager->flush();
        
        // Renvoyer le message
        $message = new SendNewsletterCampaignMessage($campaign->getId());
        $this->bus->dispatch($message);
        
        $this->logger->info("✅ Campagne #{$campaign->getId()} reprise");
    }

    /**
     * Traite un lot de la campagne (appelé par le handler)
     */
    public function processCampaignBatch(int $campaignId, array $options = []): void
    {
        $this->logger->info("Traitement du lot pour la campagne #{$campaignId}");
        
        $campaign = $this->entityManager->getRepository(NewsletterCampaign::class)->find($campaignId);
        
        if (!$campaign) {
            $this->logger->error("Campagne #{$campaignId} non trouvée");
            return;
        }
        
        // Récupérer les abonnés
        $subscribers = $campaign->getSubscribers();
        $total = $subscribers->count();
        
        $this->logger->info("{$total} destinataires à traiter");
        
        $sent = $campaign->getSentCount() ?: 0;
        $failed = $campaign->getFailedCount() ?: 0;
        
        foreach ($subscribers as $subscriber) {
            if ($sent + $failed >= $total) {
                break;
            }
            
            try {
                $result = $this->emailService->sendCampaignEmail($campaign, $subscriber);
                
                if ($result) {
                    $sent++;
                    $this->logger->info("✓ Email envoyé à {$subscriber->getEmail()}");
                } else {
                    $failed++;
                    $this->logger->warning("✗ Échec envoi à {$subscriber->getEmail()}");
                }
                
                // Mise à jour périodique
                if (($sent + $failed) % 10 === 0) {
                    $campaign->setSentCount($sent);
                    $campaign->setFailedCount($failed);
                    $campaign->setLastUpdate(new \DateTimeImmutable());
                    $this->entityManager->flush();
                }
                
            } catch (\Exception $e) {
                $failed++;
                $this->logger->error("Erreur {$subscriber->getEmail()}: " . $e->getMessage());
            }
        }
        
        // Finalisation
        $campaign->setSentCount($sent);
        $campaign->setFailedCount($failed);
        
        if ($sent + $failed >= $total) {
            if ($sent > 0) {
                $campaign->setStatus('completed');
                $this->logger->info("✅ Campagne #{$campaignId} terminée: {$sent} envoyés, {$failed} échecs");
            } else {
                $campaign->setStatus('failed');
                $this->logger->error("❌ Campagne #{$campaignId} échouée");
            }
            $campaign->setCompletedAt(new \DateTimeImmutable());
        }
        
        $campaign->setLastUpdate(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}