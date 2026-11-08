<?php
// scripts/setup-webhooks.php

namespace App\Script;

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(dirname(__DIR__) . '/.env');

/**
 * Service métier « WebhookSetup ».
 */
class WebhookSetup
{
    private string $baseUrl;
    private array $providers = [];
    
    public function __construct()
    {
        $this->baseUrl = $_ENV['APP_URL'] ?? 'https://votresite.com';
        
        $this->providers = [
            'mailgun' => [
                'api_key' => $_ENV['MAILGUN_API_KEY'] ?? null,
                'domain' => $_ENV['MAILGUN_DOMAIN'] ?? null,
            ],
            'sendgrid' => [
                'api_key' => $_ENV['SENDGRID_API_KEY'] ?? null,
            ],
            'brevo' => [
                'api_key' => $_ENV['BREVO_API_KEY'] ?? null,
            ],
        ];
    }
    
    public function setupMailgun(): void
    {
        if (empty($this->providers['mailgun']['api_key']) || empty($this->providers['mailgun']['domain'])) {
            echo "Mailgun non configuré\n";
            return;
        }
        
        $webhookUrl = $this->baseUrl . '/webhook/mailgun';
        
        $events = [
            'bounced' => true,
            'complained' => true,
            'unsubscribed' => true,
            'opened' => true,
            'clicked' => true,
            'delivered' => true,
            'dropped' => true,
        ];
        
        echo "Configuration Mailgun webhook:\n";
        echo "URL: $webhookUrl\n";
        echo "Événements: " . implode(', ', array_keys($events)) . "\n\n";
        
        // Pour configurer via l'API Mailgun
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/domains/{$this->providers['mailgun']['domain']}/webhooks");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, "api:{$this->providers['mailgun']['api_key']}");
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'url' => $webhookUrl,
            'events' => json_encode($events),
        ]));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        echo "Réponse Mailgun: $response\n\n";
    }
    
    public function setupSendgrid(): void
    {
        if (empty($this->providers['sendgrid']['api_key'])) {
            echo "SendGrid non configuré\n";
            return;
        }
        
        $webhookUrl = $this->baseUrl . '/webhook/sendgrid';
        
        echo "Configuration SendGrid webhook:\n";
        echo "URL: $webhookUrl\n\n";
        
        // Pour configurer via l'API SendGrid
        $data = [
            'enabled' => true,
            'url' => $webhookUrl,
            'group_resubscribe' => true,
            'delivered' => true,
            'group_unsubscribe' => true,
            'spam_report' => true,
            'bounce' => true,
            'deferred' => true,
            'unsubscribe' => true,
            'processed' => true,
            'open' => true,
            'click' => true,
            'dropped' => true,
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/user/webhooks/event/settings');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->providers['sendgrid']['api_key'],
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        echo "Réponse SendGrid: $response\n\n";
    }
    
    public function setupBrevo(): void
    {
        if (empty($this->providers['brevo']['api_key'])) {
            echo "Brevo non configuré\n";
            return;
        }
        
        $webhookUrl = $this->baseUrl . '/webhook/brevo';
        
        echo "Configuration Brevo webhook:\n";
        echo "URL: $webhookUrl\n\n";
        
        // Pour configurer via l'API Brevo
        $data = [
            'url' => $webhookUrl,
            'description' => 'Symfony Newsletter Webhook',
            'events' => [
                'hardBounce',
                'softBounce',
                'blocked',
                'spam',
                'invalid',
                'deferred',
                'unsubscribe',
                'opened',
                'click',
                'uniqueOpened',
            ],
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/webhooks');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: ' . $this->providers['brevo']['api_key'],
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        echo "Réponse Brevo: $response\n\n";
    }
    
    public function run(): void
    {
        echo "=== Configuration des Webhooks ===\n\n";
        
        $this->setupMailgun();
        $this->setupSendgrid();
        $this->setupBrevo();
        
        echo "\n=== URLs des webhooks ===\n";
        echo "Mailgun: " . $this->baseUrl . "/webhook/mailgun\n";
        echo "SendGrid: " . $this->baseUrl . "/webhook/sendgrid\n";
        echo "Amazon SES: " . $this->baseUrl . "/webhook/ses\n";
        echo "Brevo: " . $this->baseUrl . "/webhook/brevo\n";
        echo "Générique: " . $this->baseUrl . "/webhook/bounce\n";
        echo "Test: " . $this->baseUrl . "/webhook/test\n\n";
        
        echo "Pour tester les webhooks:\n";
        echo "curl -X POST " . $this->baseUrl . "/webhook/simulate/mailgun/bounced -H 'Content-Type: application/json' -d '{\"email\":\"test@example.com\",\"campaign_id\":1}'\n";
    }
}

$setup = new WebhookSetup();
$setup->run();