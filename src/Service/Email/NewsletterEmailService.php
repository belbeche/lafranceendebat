<?php

namespace App\Service\Email;

use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class NewsletterEmailService
{
    private string $senderEmail;
    private string $senderName;
    private string $unsubscribeUrl;
    private string $listUnsubscribeEmail;
    private string $baseUrl;

    public function __construct(
        private MailerInterface $mailer,
        string $senderEmail = 'contact@labouffonne.eu',
        string $senderName = 'La France en Débat',
        string $unsubscribeUrl = 'https://labouffonne.eu/unsubscribe',
        string $listUnsubscribeEmail = 'unsubscribe@labouffonne.eu',
        string $baseUrl = 'https://labouffonne.eu'
    ) {
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
        $this->unsubscribeUrl = $unsubscribeUrl;
        $this->listUnsubscribeEmail = $listUnsubscribeEmail;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Envoie un email de test pour une campagne.
     */
    public function sendTestCampaign(NewsletterCampaign $campaign, string $testEmail): void
    {
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L\'adresse email de test est invalide.');
        }

        if (empty($campaign->getContent())) {
            throw new \InvalidArgumentException('Le contenu de la campagne est vide.');
        }

        try {
            $email = (new Email())
                ->from(new Address(
                    $campaign->getSenderEmail() ?? $this->senderEmail,
                    $campaign->getSenderName() ?? $this->senderName
                ))
                ->replyTo($campaign->getSenderEmail() ?? $this->senderEmail)
                ->to($testEmail)
                ->subject('[Test] ' . $campaign->getSubject())
                ->html($this->addUnsubscribeFooter($campaign->getContent(), 'test-' . uniqid()));

            $this->addUnsubscribeHeaders($email, 'test-' . uniqid());

            $this->mailer->send($email);
            error_log('Email de test envoyé à : ' . $testEmail);

        } catch (TransportExceptionInterface $e) {
            error_log('Erreur test email : ' . $e->getMessage());
            throw new \RuntimeException('Erreur lors de l\'envoi de l\'email de test.', 0, $e);
        }
    }

    /**
     * Envoie un email de campagne à un abonné.
     */
    public function sendCampaignEmail(
        NewsletterCampaign $campaign, 
        NewsletterSubscriber $subscriber
    ): bool {
        if (!$subscriber->isActive()) {
            throw new \InvalidArgumentException('L\'abonné n\'est pas actif.');
        }

        if (empty($campaign->getContent())) {
            throw new \InvalidArgumentException('Le contenu de la campagne est vide.');
        }

        try {
            // Générez le contenu avec tracking
            $content = $this->addTrackingToContent(
                $campaign->getContent(),
                $campaign->getId(),
                $subscriber->getUnsubscribeToken()
            );

            // Ajouter le footer de désabonnement
            $contentWithFooter = $this->addUnsubscribeFooter(
                $content,
                $subscriber->getUnsubscribeToken()
            );

            // Préparer l'email
            $email = (new Email())
                ->from(new Address(
                    $campaign->getSenderEmail() ?? $this->senderEmail,
                    $campaign->getSenderName() ?? $this->senderName
                ))
                ->replyTo($campaign->getSenderEmail() ?? $this->senderEmail)
                ->to($subscriber->getEmail())
                ->subject($campaign->getSubject())
                ->html($contentWithFooter);

            // Ajouter les headers pour le tracking et désabonnement
            $this->addUnsubscribeHeaders($email, $subscriber->getUnsubscribeToken());

            // Envoyer l'email
            $this->mailer->send($email);

            error_log('Campagne envoyée à : ' . $subscriber->getEmail());
            return true;

        } catch (TransportExceptionInterface $e) {
            error_log('Erreur envoi à ' . $subscriber->getEmail() . ' : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Politique de la plateforme : aucun pixel de suivi d'ouverture et aucune redirection de liens dans les e-mails.
     * Le contenu est envoye tel quel (voir la politique de confidentialite, section "E-mails").
     */
    private function addTrackingToContent(string $content, int $campaignId, string $token): string
    {
        return $content;
    }

    /**
     * Ajoute les headers List-Unsubscribe pour la conformité anti-spam.
     */
    private function addUnsubscribeHeaders(Email $email, string $unsubscribeToken): void
    {
        $headers = $email->getHeaders();

        // URL de désabonnement one-click
        $unsubscribeUrl = $this->unsubscribeUrl . '?token=' . $unsubscribeToken;
        
        // Email de désabonnement
        $unsubscribeEmail = 'mailto:' . $this->listUnsubscribeEmail . '?subject=Unsubscribe&body=Please unsubscribe me';

        // Headers List-Unsubscribe (RFC 2369 et RFC 8058)
        $headers->addTextHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>, <' . $unsubscribeEmail . '>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        
        // Header List-ID pour identifier la liste
        $headers->addTextHeader('List-ID', '"' . $this->senderName . '" <' . $this->senderEmail . '>');
        
        // Headers de préférences
        $headers->addTextHeader('List-Help', '<https://labouffonne.eu/help>');
        $headers->addTextHeader('List-Owner', '<mailto:' . $this->senderEmail . '>');
        $headers->addTextHeader('List-Post', 'NO');
        $headers->addTextHeader('List-Archive', '<https://labouffonne.eu/archive>');
        
        // Header Precedence pour éviter les réponses automatiques
        $headers->addTextHeader('Precedence', 'bulk');
        
        // Headers pour la conformité CAN-SPAM et GDPR
        $headers->addTextHeader('X-Campaign-Type', 'newsletter');
        $headers->addTextHeader('X-Compliance', 'GDPR-compliant');
    }

    /**
     * Ajoute le footer de désabonnement au contenu.
     */
    private function addUnsubscribeFooter(string $content, string $unsubscribeToken): string
    {
        $footer = '
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #666666;">
                <p style="margin: 5px 0;">
                    © ' . date('Y') . ' ' . $this->senderName . '. Tous droits réservés.
                </p>
                <p style="margin: 5px 0;">
                    Cet email vous a été envoyé parce que vous êtes abonné à notre newsletter.
                </p>
                <p style="margin: 5px 0;">
                    <a href="' . $this->unsubscribeUrl . '?token=' . $unsubscribeToken . '" 
                       style="color: #4a90e2; text-decoration: none;">
                        Se désabonner
                    </a>
                    | 
                    <a href="https://labouffonne.eu/preferences" 
                       style="color: #4a90e2; text-decoration: none;">
                        Gérer mes préférences
                    </a>
                </p>
                <p style="margin: 5px 0; font-size: 11px;">
                    ' . $this->senderName . ' - ' . $this->senderEmail . '
                </p>
            </div>';

        return $content . $footer;
    }

    /**
     * Personnalise le contenu avec les informations de l'abonné.
     */
    private function personalizeContent(string $content, NewsletterSubscriber $subscriber): string
    {
        $unsubscribeToken = $subscriber->getUnsubscribeToken() ?? bin2hex(random_bytes(16));
        
        $replacements = [
            '{{first_name}}' => $subscriber->getFirstName() ?? 'Abonné',
            '{{last_name}}' => $subscriber->getLastName() ?? '',
            '{{email}}' => $subscriber->getEmail(),
            '{{full_name}}' => trim(($subscriber->getFirstName() ?? '') . ' ' . ($subscriber->getLastName() ?? '')),
            '{{unsubscribe_url}}' => $this->unsubscribeUrl . '?token=' . $unsubscribeToken,
            '{{update_preferences_url}}' => 'https://labouffonne.eu/preferences?token=' . $unsubscribeToken,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content
        );
    }

    /**
     * Envoie un batch d'emails.
     */
    private function sendBatch(NewsletterCampaign $campaign, array $batch, array &$stats): void
    {
        foreach ($batch as $subscriber) {
            try {
                $this->sendCampaignEmail($campaign, $subscriber);
                $stats['sent']++;
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'email' => $subscriber->getEmail(),
                    'error' => $e->getMessage()
                ];
                error_log('Erreur batch : ' . $e->getMessage());
            }
        }
    }

    /**
     * Envoie une campagne en masse.
     */
    public function sendBulkCampaign(NewsletterCampaign $campaign, array $subscribers, int $batchSize = 50): array
    {
        $stats = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $total = count($subscribers);
        $processed = 0;

        foreach (array_chunk($subscribers, $batchSize) as $batch) {
            $this->sendBatch($campaign, $batch, $stats);
            $processed += count($batch);
            
            // Pause pour éviter de surcharger le serveur SMTP
            if ($processed < $total) {
                sleep(1);
            }
        }

        return $stats;
    }

    /**
     * Génère un token de désabonnement unique.
     */
    public function generateUnsubscribeToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}