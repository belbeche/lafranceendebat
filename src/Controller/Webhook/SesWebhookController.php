<?php
// src/Controller/Webhook/SesWebhookController.php

namespace App\Controller\Webhook;

use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use App\Service\CampaignTrackingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

/**
 * Contrôleur HTTP "SesWebhookController".
 *
 * Routes :
 *  - POST /webhook/ses/notification (webhook_ses_notification)
 *  - GET /webhook/ses/verify (webhook_ses_verify)
 *  - GET,POST /webhook/ses/test (webhook_ses_test)
 *  - GET /webhook/ses/setup (webhook_ses_setup)
 */
#[Route('/webhook/ses')]
class SesWebhookController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private CampaignTrackingService $trackingService;
    private string $sesVerificationToken;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        CampaignTrackingService $trackingService,
        string $sesVerificationToken
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->trackingService = $trackingService;
        $this->sesVerificationToken = $sesVerificationToken;
    }

    /**
     * Endpoint principal pour les notifications Amazon SES
     * Supporte les types: SubscriptionConfirmation, Notification, Bounce, Complaint, Delivery
     */
    #[Route('/notification', name: 'webhook_ses_notification', methods: ['POST'])]
    public function handleNotification(Request $request): Response
    {
        $content = $request->getContent();
        $this->logger->info('SES Webhook reçu', ['content' => $content]);

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!isset($data['Type'])) {
                throw new BadRequestHttpException('Type de notification manquant');
            }

            $notificationType = $data['Type'];
            
            $this->logger->info('Traitement notification SES', [
                'type' => $notificationType,
                'messageId' => $data['MessageId'] ?? 'N/A'
            ]);

            switch ($notificationType) {
                case 'SubscriptionConfirmation':
                    return $this->handleSubscriptionConfirmation($data);
                    
                case 'Notification':
                    return $this->handleSnsNotification($data);
                    
                case 'Bounce':
                    return $this->handleBounce($data);
                    
                case 'Complaint':
                    return $this->handleComplaint($data);
                    
                case 'Delivery':
                    return $this->handleDelivery($data);
                    
                case 'Send':
                    return $this->handleSend($data);
                    
                case 'Reject':
                    return $this->handleReject($data);
                    
                case 'Open':
                    return $this->handleOpen($data);
                    
                case 'Click':
                    return $this->handleClick($data);
                    
                default:
                    $this->logger->warning('Type de notification SES non supporté', [
                        'type' => $notificationType
                    ]);
                    return new JsonResponse(['status' => 'ignored', 'message' => 'Notification type not supported'], 200);
            }
            
        } catch (\JsonException $e) {
            $this->logger->error('Erreur JSON SES Webhook', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        } catch (\Exception $e) {
            $this->logger->error('Erreur traitement SES Webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new JsonResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Gère la confirmation d'abonnement SNS
     */
    private function handleSubscriptionConfirmation(array $data): JsonResponse
    {
        if (!isset($data['SubscribeURL'])) {
            throw new BadRequestHttpException('SubscribeURL manquant');
        }

        $subscribeUrl = $data['SubscribeURL'];
        
        $this->logger->info('Confirmation abonnement SNS', ['url' => $subscribeUrl]);
        
        // Confirmer automatiquement l'abonnement
        $ch = curl_init($subscribeUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $this->logger->info('Abonnement SNS confirmé avec succès');
            return new JsonResponse(['status' => 'confirmed'], 200);
        } else {
            $this->logger->error('Échec confirmation abonnement SNS', [
                'http_code' => $httpCode,
                'response' => $response
            ]);
            return new JsonResponse(['error' => 'Subscription confirmation failed'], 500);
        }
    }

    /**
     * Gère les notifications SNS encapsulées
     */
    private function handleSnsNotification(array $data): JsonResponse
    {
        if (!isset($data['Message'])) {
            throw new BadRequestHttpException('Message manquant dans notification SNS');
        }

        $message = json_decode($data['Message'], true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Le message pourrait être une chaîne simple pour les erreurs
            $this->logger->warning('Message SNS non-JSON', ['message' => $data['Message']]);
            return new JsonResponse(['status' => 'ignored'], 200);
        }

        if (!isset($message['notificationType'])) {
            throw new BadRequestHttpException('notificationType manquant dans message SNS');
        }

        $notificationType = $message['notificationType'];
        
        $this->logger->info('Notification SNS traitée', [
            'notificationType' => $notificationType,
            'messageId' => $message['mail']['messageId'] ?? 'N/A'
        ]);

        switch ($notificationType) {
            case 'Bounce':
                return $this->handleBounce($message);
                
            case 'Complaint':
                return $this->handleComplaint($message);
                
            case 'Delivery':
                return $this->handleDelivery($message);
                
            case 'Send':
                return $this->handleSend($message);
                
            case 'Reject':
                return $this->handleReject($message);
                
            case 'Open':
                return $this->handleOpen($message);
                
            case 'Click':
                return $this->handleClick($message);
                
            default:
                $this->logger->warning('Type de notification SNS non supporté', [
                    'type' => $notificationType
                ]);
                return new JsonResponse(['status' => 'ignored'], 200);
        }
    }

    /**
     * Gère les rebonds (Bounce)
     */
    private function handleBounce(array $data): JsonResponse
    {
        $validator = Validation::createValidator();
        
        $constraints = new Assert\Collection([
            'bounce' => new Assert\Collection([
                'bounceType' => new Assert\Choice(['Permanent', 'Transient']),
                'bounceSubType' => new Assert\NotBlank(),
                'bouncedRecipients' => new Assert\All([
                    new Assert\Collection([
                        'emailAddress' => new Assert\Email(),
                        'status' => new Assert\Optional(),
                        'action' => new Assert\Optional(),
                        'diagnosticCode' => new Assert\Optional(),
                    ])
                ]),
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'feedbackId' => new Assert\Optional(),
                'reportingMTA' => new Assert\Optional(),
            ]),
            'mail' => new Assert\Optional([
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'messageId' => new Assert\NotBlank(),
                'source' => new Assert\Email(),
                'sourceArn' => new Assert\Optional(),
                'sendingAccountId' => new Assert\Optional(),
                'destination' => new Assert\All([new Assert\Email()]),
                'headersTruncated' => new Assert\Optional(),
                'headers' => new Assert\Optional(),
                'commonHeaders' => new Assert\Optional(),
            ])
        ]);

        $violations = $validator->validate($data, $constraints);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            $this->logger->error('Validation bounce SES échouée', ['errors' => $errors]);
            throw new BadRequestHttpException('Données bounce invalides: ' . implode(', ', $errors));
        }

        $bounce = $data['bounce'];
        $mail = $data['mail'] ?? [];
        
        $bounceType = $bounce['bounceType'] === 'Permanent' ? 'hard' : 'soft';
        $bounceSubType = $bounce['bounceSubType'];
        $timestamp = new \DateTime($bounce['timestamp']);
        
        $this->logger->info('Traitement bounce SES', [
            'type' => $bounceType,
            'subType' => $bounceSubType,
            'recipients' => count($bounce['bouncedRecipients']),
            'messageId' => $mail['messageId'] ?? 'N/A'
        ]);

        $processedCount = 0;
        $errors = [];

        foreach ($bounce['bouncedRecipients'] as $recipient) {
            try {
                $email = $recipient['emailAddress'];
                $diagnosticCode = $recipient['diagnosticCode'] ?? $bounceSubType;
                
                // Essayer de trouver la campagne via les headers
                $campaign = $this->findCampaignFromHeaders($mail['headers'] ?? [], $mail['commonHeaders'] ?? []);
                
                if (!$campaign) {
                    // Essayer de trouver la dernière campagne envoyée à cet email
                    $campaign = $this->findLastCampaignForEmail($email);
                }

                if ($campaign) {
                    // Trouver le subscriber
                    $subscriber = $this->entityManager->getRepository(NewsletterSubscriber::class)
                        ->findOneBy(['email' => $email]);
                    
                    $this->trackingService->trackBounce(
                        $campaign,
                        $email,
                        $bounceType,
                        $diagnosticCode
                    );
                    
                    $this->logger->info('Bounce traité', [
                        'email' => $email,
                        'campaign_id' => $campaign->getId(),
                        'bounce_type' => $bounceType
                    ]);
                    
                    $processedCount++;
                    
                    // Enregistrer le bounce dans un fichier log
                    $this->logBounceToFile($campaign, $email, $bounceType, $diagnosticCode, $timestamp);
                    
                } else {
                    $this->logger->warning('Campagne non trouvée pour bounce', [
                        'email' => $email,
                        'messageId' => $mail['messageId'] ?? 'N/A'
                    ]);
                    
                    // Enregistrer quand même le bounce pour suivi
                    $this->logOrphanBounce($email, $bounceType, $diagnosticCode, $mail);
                }
                
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
                $this->logger->error('Erreur traitement bounce individuel', [
                    'email' => $email ?? 'N/A',
                    'error' => $e->getMessage()
                ]);
            }
        }

        $response = [
            'status' => 'processed',
            'processed' => $processedCount,
            'errors' => $errors
        ];

        return new JsonResponse($response, 200);
    }

    /**
     * Gère les plaintes spam (Complaint)
     */
    private function handleComplaint(array $data): JsonResponse
    {
        $validator = Validation::createValidator();
        
        $constraints = new Assert\Collection([
            'complaint' => new Assert\Collection([
                'complainedRecipients' => new Assert\All([
                    new Assert\Collection([
                        'emailAddress' => new Assert\Email(),
                    ])
                ]),
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'feedbackId' => new Assert\Optional(),
                'complaintFeedbackType' => new Assert\Optional(),
                'userAgent' => new Assert\Optional(),
                'arrivalDate' => new Assert\Optional([new Assert\DateTime(\DateTime::RFC3339)]),
            ]),
            'mail' => new Assert\Optional([
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'messageId' => new Assert\NotBlank(),
                'source' => new Assert\Email(),
                'sourceArn' => new Assert\Optional(),
                'sendingAccountId' => new Assert\Optional(),
                'destination' => new Assert\All([new Assert\Email()]),
                'headersTruncated' => new Assert\Optional(),
                'headers' => new Assert\Optional(),
                'commonHeaders' => new Assert\Optional(),
            ])
        ]);

        $violations = $validator->validate($data, $constraints);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            $this->logger->error('Validation complaint SES échouée', ['errors' => $errors]);
            throw new BadRequestHttpException('Données complaint invalides: ' . implode(', ', $errors));
        }

        $complaint = $data['complaint'];
        $mail = $data['mail'] ?? [];
        $timestamp = new \DateTime($complaint['timestamp']);
        
        $this->logger->info('Traitement complaint SES', [
            'recipients' => count($complaint['complainedRecipients']),
            'feedbackType' => $complaint['complaintFeedbackType'] ?? 'N/A',
            'messageId' => $mail['messageId'] ?? 'N/A'
        ]);

        $processedCount = 0;
        $errors = [];

        foreach ($complaint['complainedRecipients'] as $recipient) {
            try {
                $email = $recipient['emailAddress'];
                
                // Trouver la campagne
                $campaign = $this->findCampaignFromHeaders($mail['headers'] ?? [], $mail['commonHeaders'] ?? []);
                
                if (!$campaign) {
                    $campaign = $this->findLastCampaignForEmail($email);
                }

                if ($campaign) {
                    // Trouver le subscriber
                    $subscriber = $this->entityManager->getRepository(NewsletterSubscriber::class)
                        ->findOneBy(['email' => $email]);
                    
                    if ($subscriber) {
                        $this->trackingService->trackSpamReport($campaign, $subscriber);
                        
                        $this->logger->info('Complaint traité', [
                            'email' => $email,
                            'campaign_id' => $campaign->getId(),
                            'feedback_type' => $complaint['complaintFeedbackType'] ?? 'N/A'
                        ]);
                        
                        $processedCount++;
                        
                        // Enregistrer la plainte dans un fichier log
                        $this->logComplaintToFile($campaign, $email, $complaint, $timestamp);
                        
                    } else {
                        $this->logger->warning('Subscriber non trouvé pour complaint', ['email' => $email]);
                    }
                } else {
                    $this->logger->warning('Campagne non trouvée pour complaint', [
                        'email' => $email,
                        'messageId' => $mail['messageId'] ?? 'N/A'
                    ]);
                }
                
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
                $this->logger->error('Erreur traitement complaint individuel', [
                    'email' => $email ?? 'N/A',
                    'error' => $e->getMessage()
                ]);
            }
        }

        $response = [
            'status' => 'processed',
            'processed' => $processedCount,
            'errors' => $errors
        ];

        return new JsonResponse($response, 200);
    }

    /**
     * Gère les notifications de livraison (Delivery)
     */
    private function handleDelivery(array $data): JsonResponse
    {
        $validator = Validation::createValidator();
        
        $constraints = new Assert\Collection([
            'delivery' => new Assert\Collection([
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'processingTimeMillis' => new Assert\Optional(),
                'recipients' => new Assert\All([new Assert\Email()]),
                'smtpResponse' => new Assert\Optional(),
                'reportingMTA' => new Assert\Optional(),
            ]),
            'mail' => new Assert\Optional([
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'messageId' => new Assert\NotBlank(),
                'source' => new Assert\Email(),
                'sourceArn' => new Assert\Optional(),
                'sendingAccountId' => new Assert\Optional(),
                'destination' => new Assert\All([new Assert\Email()]),
                'headersTruncated' => new Assert\Optional(),
                'headers' => new Assert\Optional(),
                'commonHeaders' => new Assert\Optional(),
            ])
        ]);

        $violations = $validator->validate($data, $constraints);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            $this->logger->error('Validation delivery SES échouée', ['errors' => $errors]);
            throw new BadRequestHttpException('Données delivery invalides: ' . implode(', ', $errors));
        }

        $delivery = $data['delivery'];
        $mail = $data['mail'] ?? [];
        $timestamp = new \DateTime($delivery['timestamp']);
        
        $this->logger->debug('Notification delivery SES', [
            'recipients' => count($delivery['recipients']),
            'processingTime' => $delivery['processingTimeMillis'] ?? 'N/A',
            'messageId' => $mail['messageId'] ?? 'N/A'
        ]);

        // Pour les deliveries, on peut simplement logger
        // ou mettre à jour des statistiques si nécessaire
        foreach ($delivery['recipients'] as $email) {
            $this->logger->debug('Email livré', [
                'email' => $email,
                'messageId' => $mail['messageId'] ?? 'N/A',
                'timestamp' => $timestamp->format('Y-m-d H:i:s')
            ]);
        }

        return new JsonResponse(['status' => 'logged'], 200);
    }

    /**
     * Gère les notifications d'envoi (Send)
     */
    private function handleSend(array $data): JsonResponse
    {
        $this->logger->debug('Notification send SES', [
            'messageId' => $data['mail']['messageId'] ?? 'N/A'
        ]);

        return new JsonResponse(['status' => 'logged'], 200);
    }

    /**
     * Gère les rejets (Reject)
     */
    private function handleReject(array $data): JsonResponse
    {
        $validator = Validation::createValidator();
        
        $constraints = new Assert\Collection([
            'reject' => new Assert\Collection([
                'reason' => new Assert\NotBlank(),
            ]),
            'mail' => new Assert\Optional([
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'messageId' => new Assert\NotBlank(),
                'source' => new Assert\Email(),
                'sourceArn' => new Assert\Optional(),
                'sendingAccountId' => new Assert\Optional(),
                'destination' => new Assert\All([new Assert\Email()]),
                'headersTruncated' => new Assert\Optional(),
                'headers' => new Assert\Optional(),
                'commonHeaders' => new Assert\Optional(),
            ])
        ]);

        $violations = $validator->validate($data, $constraints);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            $this->logger->error('Validation reject SES échouée', ['errors' => $errors]);
            throw new BadRequestHttpException('Données reject invalides: ' . implode(', ', $errors));
        }

        $reject = $data['reject'];
        $mail = $data['mail'] ?? [];
        
        $this->logger->warning('Email rejeté par SES', [
            'reason' => $reject['reason'],
            'messageId' => $mail['messageId'] ?? 'N/A',
            'source' => $mail['source'] ?? 'N/A'
        ]);

        return new JsonResponse(['status' => 'logged'], 200);
    }

    /**
     * Gère les ouvertures (Open) - Si configuré dans SES
     */
    private function handleOpen(array $data): JsonResponse
    {
        $validator = Validation::createValidator();
        
        $constraints = new Assert\Collection([
            'open' => new Assert\Collection([
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'ipAddress' => new Assert\Optional(),
                'userAgent' => new Assert\Optional(),
            ]),
            'mail' => new Assert\Optional([
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'messageId' => new Assert\NotBlank(),
                'source' => new Assert\Email(),
                'sourceArn' => new Assert\Optional(),
                'sendingAccountId' => new Assert\Optional(),
                'destination' => new Assert\All([new Assert\Email()]),
                'headersTruncated' => new Assert\Optional(),
                'headers' => new Assert\Optional(),
                'commonHeaders' => new Assert\Optional(),
            ])
        ]);

        $violations = $validator->validate($data, $constraints);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            $this->logger->error('Validation open SES échouée', ['errors' => $errors]);
            throw new BadRequestHttpException('Données open invalides: ' . implode(', ', $errors));
        }

        $open = $data['open'];
        $mail = $data['mail'] ?? [];
        $timestamp = new \DateTime($open['timestamp']);
        
        // Chercher l'email dans les destinations
        $email = $mail['destination'][0] ?? null;
        
        if ($email) {
            // Trouver la campagne
            $campaign = $this->findCampaignFromHeaders($mail['headers'] ?? [], $mail['commonHeaders'] ?? []);
            
            if ($campaign) {
                // Trouver le subscriber
                $subscriber = $this->entityManager->getRepository(NewsletterSubscriber::class)
                    ->findOneBy(['email' => $email]);
                
                if ($subscriber) {
                    $this->trackingService->trackOpen($campaign, $subscriber);
                    
                    $this->logger->info('Ouverture SES trackée', [
                        'email' => $email,
                        'campaign_id' => $campaign->getId(),
                        'ip' => $open['ipAddress'] ?? 'N/A',
                        'userAgent' => $open['userAgent'] ?? 'N/A'
                    ]);
                }
            }
        }

        return new JsonResponse(['status' => 'processed'], 200);
    }

    /**
     * Gère les clics (Click) - Si configuré dans SES
     */
    private function handleClick(array $data): JsonResponse
    {
        $validator = Validation::createValidator();
        
        $constraints = new Assert\Collection([
            'click' => new Assert\Collection([
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'ipAddress' => new Assert\Optional(),
                'userAgent' => new Assert\Optional(),
                'link' => new Assert\Optional(),
            ]),
            'mail' => new Assert\Optional([
                'timestamp' => new Assert\DateTime(\DateTime::RFC3339),
                'messageId' => new Assert\NotBlank(),
                'source' => new Assert\Email(),
                'sourceArn' => new Assert\Optional(),
                'sendingAccountId' => new Assert\Optional(),
                'destination' => new Assert\All([new Assert\Email()]),
                'headersTruncated' => new Assert\Optional(),
                'headers' => new Assert\Optional(),
                'commonHeaders' => new Assert\Optional(),
            ])
        ]);

        $violations = $validator->validate($data, $constraints);
        
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            $this->logger->error('Validation click SES échouée', ['errors' => $errors]);
            throw new BadRequestHttpException('Données click invalides: ' . implode(', ', $errors));
        }

        $click = $data['click'];
        $mail = $data['mail'] ?? [];
        $timestamp = new \DateTime($click['timestamp']);
        
        // Chercher l'email dans les destinations
        $email = $mail['destination'][0] ?? null;
        $link = $click['link'] ?? null;
        
        if ($email && $link) {
            // Trouver la campagne
            $campaign = $this->findCampaignFromHeaders($mail['headers'] ?? [], $mail['commonHeaders'] ?? []);
            
            if ($campaign) {
                // Trouver le subscriber
                $subscriber = $this->entityManager->getRepository(NewsletterSubscriber::class)
                    ->findOneBy(['email' => $email]);
                
                if ($subscriber) {
                    $this->trackingService->trackClick($campaign, $link, $subscriber);
                    
                    $this->logger->info('Clic SES tracké', [
                        'email' => $email,
                        'campaign_id' => $campaign->getId(),
                        'link' => $link,
                        'ip' => $click['ipAddress'] ?? 'N/A',
                        'userAgent' => $click['userAgent'] ?? 'N/A'
                    ]);
                }
            }
        }

        return new JsonResponse(['status' => 'processed'], 200);
    }

    /**
     * Cherche une campagne dans les headers de l'email
     */
    private function findCampaignFromHeaders(array $headers, array $commonHeaders): ?NewsletterCampaign
    {
        // Chercher dans les headers personnalisés
        foreach ($headers as $header) {
            if (isset($header['name']) && isset($header['value'])) {
                if (strtolower($header['name']) === 'x-campaign-id') {
                    $campaignId = (int) $header['value'];
                    return $this->entityManager->getRepository(NewsletterCampaign::class)->find($campaignId);
                }
                
                if (strtolower($header['name']) === 'x-message-id') {
                    // Format: campaign-{id}-{timestamp}-{random}
                    $value = $header['value'];
                    if (preg_match('/^campaign-(\d+)-/', $value, $matches)) {
                        $campaignId = (int) $matches[1];
                        return $this->entityManager->getRepository(NewsletterCampaign::class)->find($campaignId);
                    }
                }
            }
        }
        
        // Chercher dans les headers communs (subject peut contenir l'ID)
        if (isset($commonHeaders['subject'])) {
            $subject = $commonHeaders['subject'];
            // Format: [CAMPAIGN-123] Sujet du message
            if (preg_match('/\[CAMPAIGN-(\d+)\]/', $subject, $matches)) {
                $campaignId = (int) $matches[1];
                return $this->entityManager->getRepository(NewsletterCampaign::class)->find($campaignId);
            }
        }
        
        return null;
    }

    /**
     * Trouve la dernière campagne envoyée à un email donné
     */
    private function findLastCampaignForEmail(string $email): ?NewsletterCampaign
    {
        $subscriber = $this->entityManager->getRepository(NewsletterSubscriber::class)
            ->findOneBy(['email' => $email]);
        
        if (!$subscriber) {
            return null;
        }
        
        return $this->entityManager->getRepository(NewsletterCampaign::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.subscribers', 's')
            ->where('s.id = :subscriberId')
            ->andWhere('c.status = :status')
            ->orderBy('c.createdAt', 'DESC')
            ->setParameter('subscriberId', $subscriber->getId())
            ->setParameter('status', 'completed')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Loggue un bounce dans un fichier
     */
    private function logBounceToFile(
        NewsletterCampaign $campaign,
        string $email,
        string $bounceType,
        string $reason,
        \DateTimeInterface $timestamp
    ): void {
        $logDir = $this->getParameter('kernel.project_dir') . '/var/log/ses/bounces/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . date('Y-m-d') . '.log';
        $logMessage = sprintf(
            "[%s] BOUNCE - Campaign #%d - %s - %s bounce - Reason: %s\n",
            $timestamp->format('Y-m-d H:i:s'),
            $campaign->getId(),
            $email,
            $bounceType,
            $reason
        );
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Loggue une plainte dans un fichier
     */
    private function logComplaintToFile(
        NewsletterCampaign $campaign,
        string $email,
        array $complaintData,
        \DateTimeInterface $timestamp
    ): void {
        $logDir = $this->getParameter('kernel.project_dir') . '/var/log/ses/complaints/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . date('Y-m-d') . '.log';
        $feedbackType = $complaintData['complaintFeedbackType'] ?? 'Unknown';
        $userAgent = $complaintData['userAgent'] ?? 'Unknown';
        
        $logMessage = sprintf(
            "[%s] COMPLAINT - Campaign #%d - %s - Type: %s - UserAgent: %s\n",
            $timestamp->format('Y-m-d H:i:s'),
            $campaign->getId(),
            $email,
            $feedbackType,
            $userAgent
        );
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Loggue un bounce orphelin (sans campagne connue)
     */
    private function logOrphanBounce(
        string $email,
        string $bounceType,
        string $reason,
        array $mailData
    ): void {
        $logDir = $this->getParameter('kernel.project_dir') . '/var/log/ses/orphan-bounces/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . date('Y-m-d') . '.log';
        $messageId = $mailData['messageId'] ?? 'N/A';
        $source = $mailData['source'] ?? 'N/A';
        
        $logMessage = sprintf(
            "[%s] ORPHAN BOUNCE - %s - %s bounce - Reason: %s - MessageId: %s - Source: %s\n",
            date('Y-m-d H:i:s'),
            $email,
            $bounceType,
            $reason,
            $messageId,
            $source
        );
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Endpoint de vérification pour AWS SNS
     */
    #[Route('/verify', name: 'webhook_ses_verify', methods: ['GET'])]
    public function verifyEndpoint(Request $request): Response
    {
        $token = $request->query->get('token');
        
        if ($token === $this->sesVerificationToken) {
            $this->logger->info('Verification endpoint appelé avec token valide');
            return new Response('VERIFICATION_SUCCESS', 200);
        }
        
        $this->logger->warning('Verification endpoint appelé avec token invalide', [
            'token_reçu' => $token,
            'token_attendu' => $this->sesVerificationToken
        ]);
        
        return new Response('INVALID_TOKEN', 403);
    }

    /**
     * Endpoint pour tester la configuration SES
     */
    #[Route('/test', name: 'webhook_ses_test', methods: ['GET', 'POST'])]
    public function testEndpoint(Request $request): JsonResponse
    {
        // Vérifier si on est en environnement de développement
        if ($this->getParameter('kernel.environment') !== 'dev') {
            throw $this->createAccessDeniedException('Test endpoint seulement en dev');
        }

        if ($request->isMethod('POST')) {
            // Simuler une notification SES
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse(['error' => 'Invalid JSON'], 400);
            }

            return $this->handleNotification($request);
        }

        // GET - Afficher les informations de test
        return new JsonResponse([
            'status' => 'ok',
            'environment' => $this->getParameter('kernel.environment'),
            'timestamp' => date('c'),
            'endpoints' => [
                'notification' => $this->generateUrl('webhook_ses_notification', [], true),
                'verify' => $this->generateUrl('webhook_ses_verify', [], true),
                'test' => $this->generateUrl('webhook_ses_test', [], true),
            ],
            'instructions' => 'Utilisez POST avec un payload JSON SES pour tester'
        ]);
    }

    /**
     * Script pour configurer les webhooks SES
     */
    #[Route('/setup', name: 'webhook_ses_setup', methods: ['GET'])]
    public function setupInstructions(): Response
    {
        $webhookUrl = $this->generateUrl('webhook_ses_notification', [], true);
        $verifyUrl = $this->generateUrl('webhook_ses_verify', [], true);
        
        $instructions = <<<EOF
# Configuration Amazon SES Webhooks

## 1. Configuration SNS (Simple Notification Service)

### Créer un Topic SNS
1. Allez dans AWS Console > SNS
2. Créez un nouveau Topic (ex: "ses-notifications")
3. Notez l'ARN du Topic

### Configurer les notifications SES
1. Allez dans AWS Console > SES
2. Dans "Email Sending" > "Configuration Sets"
3. Créez un Configuration Set (ex: "newsletter-tracking")
4. Ajoutez les Event Destinations:
   - Bounce
   - Complaint  
   - Delivery
   - Send
   - Reject
   - Open (optionnel)
   - Click (optionnel)
5. Pour chaque Event Destination, sélectionnez "Amazon SNS"
6. Choisissez le Topic SNS créé précédemment

### Abonner le webhook au Topic SNS
1. Dans le Topic SNS, créez un Subscription:
   - Protocol: HTTPS
   - Endpoint: {$webhookUrl}
   - Enable raw message delivery: Oui

## 2. Configuration dans Symfony

### Variables d'environnement (.env)
EOF;

        return new Response(nl2br($instructions), 200, ['Content-Type' => 'text/html']);
    }
}
