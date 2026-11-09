<?php
// src/Command/SesSetupCommand.php

namespace App\Command;

use Aws\Ses\SesClient;
use Aws\Sns\SnsClient;
use Psr\Log\LoggerInterface;
use Aws\Credentials\Credentials;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Commande console "SesSetupCommand".
 *
 * Lancer : php bin/console ses_webhook_verify
 */
class SesSetupCommand extends Command
{
    protected static $defaultName = 'app:ses:setup-webhook';
    
    private ParameterBagInterface $params;
    private string $webhookUrl;
    private string $verifyUrl;
    private string $sesVerificationToken;
    private \Psr\Log\LoggerInterface $logger;
    
    public function __construct(ParameterBagInterface $params, LoggerInterface $logger, string $projectDir)
    {
        parent::__construct();
        $this->params = $params;
        $this->logger = $logger;
        
        // Construire les URLs
        $this->webhookUrl = ($this->params->has('app_url') ? $this->params->get('app_url') : '') . '/webhook/ses/notification';
        $this->verifyUrl = ($this->params->has('app_url') ? $this->params->get('app_url') : '') . '/webhook/ses/verify';
        $this->sesVerificationToken = $this->params->has('SES_WEBHOOK_TOKEN') ? (string) $this->params->get('SES_WEBHOOK_TOKEN') : '';
    }
    
    protected function configure()
    {
        $this
            ->setDescription('Configure automatiquement les webhooks Amazon SES')
            ->setHelp('Cette commande configure les notifications SNS pour Amazon SES');
    }

    #[Route('/webhook/ses/verify', name: 'ses_webhook_verify', methods: ['GET'])]
    public function verify(Request $request): Response
    {
        $token = $request->query->get('token');
        
        if ($token !== $this->sesVerificationToken) {
            return new Response('Invalid token', Response::HTTP_FORBIDDEN);
        }
        
        return new Response('OK', Response::HTTP_OK);
    }

    #[Route('/webhook/ses/notification', name: 'ses_webhook_notification', methods: ['POST'])]
    public function notification(Request $request): Response
    {
        // Logique de traitement des notifications SES
        $content = $request->getContent();
        $data = json_decode($content, true);
        
        $this->logger->info('SES Webhook received', ['data' => $data]);
        
        // Validation du token si nécessaire
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader !== 'Bearer ' . $this->sesVerificationToken) {
            $this->logger->warning('Invalid authorization header');
            return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }
        
        // Traitement de la notification
        return new Response('OK', Response::HTTP_OK);
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Configuration Amazon SES Webhooks');
        
        // Vérifier les variables d'environnement
        $requiredEnvVars = [
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY',
            'AWS_REGION',
            'SES_WEBHOOK_TOKEN',
            'APP_URL'
        ];
        
        foreach ($requiredEnvVars as $var) {
            if (!$this->params->has($var) || empty($this->params->get($var))) {
                $io->error("Variable d'environnement manquante: $var");
                return Command::FAILURE;
            }
        }
        
        $awsKey = $this->params->get('AWS_ACCESS_KEY_ID');
        $awsSecret = $this->params->get('AWS_SECRET_ACCESS_KEY');
        $awsRegion = $this->params->get('AWS_REGION');
        $webhookToken = $this->params->has('SES_WEBHOOK_TOKEN') ? $this->params->get('SES_WEBHOOK_TOKEN') : '';
        
        // Initialiser les clients AWS
        $credentials = new Credentials($awsKey, $awsSecret);
        
        $snsClient = new SnsClient([
            'version' => 'latest',
            'region' => $awsRegion,
            'credentials' => $credentials
        ]);
        
        $sesClient = new SesClient([
            'version' => 'latest',
            'region' => $awsRegion,
            'credentials' => $credentials
        ]);
        
        try {
            // 1. Créer le Topic SNS
            $io->section('1. Création du Topic SNS');
            
            $topicName = 'ses-newsletter-notifications-' . date('Ymd-His');
            $result = $snsClient->createTopic([
                'Name' => $topicName
            ]);
            
            $topicArn = $result['TopicArn'];
            $io->success("Topic créé: $topicArn");
            
            // 2. S'abonner au Topic
            $io->section('2. Abonnement du webhook au Topic');
            
            $result = $snsClient->subscribe([
                'Protocol' => 'https',
                'Endpoint' => $this->webhookUrl,
                'TopicArn' => $topicArn,
                'Attributes' => [
                    'RawMessageDelivery' => 'true'
                ]
            ]);
            
            $subscriptionArn = $result['SubscriptionArn'];
            $io->success("Webhook abonné: $subscriptionArn");
            
            // 3. Créer le Configuration Set dans SES
            $io->section('3. Création du Configuration Set SES');
            
            $configSetName = 'newsletter-config-' . date('Ymd-His');
            $sesClient->createConfigurationSet([
                'ConfigurationSet' => [
                    'Name' => $configSetName
                ]
            ]);
            
            $io->success("Configuration Set créé: $configSetName");
            
            // 4. Ajouter les Event Destinations
            $io->section('4. Configuration des Event Destinations');
            
            $eventTypes = ['Bounce', 'Complaint', 'Delivery', 'Send', 'Reject'];
            
            foreach ($eventTypes as $eventType) {
                $sesClient->createConfigurationSetEventDestination([
                    'ConfigurationSetName' => $configSetName,
                    'EventDestination' => [
                        'Name' => $eventType . '-Destination',
                        'Enabled' => true,
                        'MatchingEventTypes' => [$eventType],
                        'SNSDestination' => [
                            'TopicARN' => $topicArn
                        ]
                    ]
                ]);
                
                $io->text("✓ $eventType configuré");
            }
            
            // 5. Configurer les événements optionnels (Open/Click)
            $io->section('5. Configuration des événements optionnels');
            
            $optionalEvents = ['Open', 'Click'];
            foreach ($optionalEvents as $event) {
                try {
                    $sesClient->createConfigurationSetEventDestination([
                        'ConfigurationSetName' => $configSetName,
                        'EventDestination' => [
                            'Name' => $event . '-Destination',
                            'Enabled' => true,
                            'MatchingEventTypes' => [$event],
                            'SNSDestination' => [
                                'TopicARN' => $topicArn
                            ],
                            'KinesisFirehoseDestination' => [
                                'DeliveryStreamARN' => 'arn:aws:firehose:region:account:deliverystream/name',
                                'IAMRoleARN' => 'arn:aws:iam::account:role/role-name'
                            ]
                        ]
                    ]);
                    
                    $io->text("✓ $event configuré (nécessite Firehose)");
                } catch (\Exception $e) {
                    $io->warning("$event non configuré: " . $e->getMessage());
                }
            }
            
            // 6. Résumé de la configuration
            $io->section('6. Résumé de la configuration');
            
            $summary = [
                'Topic SNS ARN' => $topicArn,
                'Configuration Set' => $configSetName,
                'Webhook URL' => $this->webhookUrl,
                'Verify URL' => $this->verifyUrl . '?token=' . $webhookToken,
                'Event Types' => implode(', ', array_merge($eventTypes, $optionalEvents))
            ];
            
            $io->table(['Paramètre', 'Valeur'], array_map(
                fn($k, $v) => [$k, $v],
                array_keys($summary),
                array_values($summary)
            ));
            
            // 7. Instructions pour utiliser la configuration
            $io->section('7. Instructions d\'utilisation');
            
            $io->text([
                'Pour utiliser cette configuration dans vos emails:',
                '',
                '1. Dans votre code d\'envoi d\'email, ajoutez:',
                '   X-SES-CONFIGURATION-SET: ' . $configSetName,
                '',
                '2. Pour les campagnes newsletter, ajoutez aussi:',
                '   X-Campaign-ID: {campaign_id}',
                '',
                '3. Testez la configuration:',
                '   ' . $this->params->get('APP_URL') . '/admin/webhooks/ses',
                '',
                '4. Configurez les variables d\'environnement:',
                '   AWS_SNS_TOPIC_ARN=' . $topicArn,
                '   AWS_SES_CONFIGURATION_SET=' . $configSetName,
                '   SES_WEBHOOK_TOKEN=' . $webhookToken
            ]);
            
            // 8. Sauvegarder la configuration
            $configFile = $this->params->get('kernel.project_dir') . '/config/ses-config.json';
            file_put_contents($configFile, json_encode($summary, JSON_PRETTY_PRINT));
            
            $io->success("Configuration sauvegardée dans: $configFile");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Erreur lors de la configuration: ' . $e->getMessage());
            $io->text('Trace: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}