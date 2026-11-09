<?php

// src/Command/SendNewsletterCommand.php

namespace App\Command;

use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mime\Address;
use Twig\Environment;

#[AsCommand(
    name: 'app:newsletter:send',
    description: 'Send newsletter campaigns',
    hidden: false
)]
/**
 * Commande console "SendNewsletterCommand".
 *
 * Lancer : php bin/console app:newsletter:send. Send newsletter campaigns
 */
class SendNewsletterCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;
    private Environment $twig;

    public function __construct(
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        Environment $twig
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
        $this->twig = $twig;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('campaign-id', InputArgument::REQUIRED, 'The campaign ID')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Number of emails per batch', 50)
            ->addOption('delay', 'd', InputOption::VALUE_REQUIRED, 'Delay between emails in microseconds', 100000)
            ->addOption('test', 't', InputOption::VALUE_NONE, 'Test mode (send to admin only)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force send even if campaign is not pending')
            // NE PAS AJOUTER -v ou --verbose ! C'est déjà inclus par défaut dans Symfony
            ->setHelp('This command sends newsletter emails in batches to avoid timeout and rate limiting.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // PAS BESOIN de gérer -v manuellement, Symfony le fait automatiquement
        
        $campaignId = (int) $input->getArgument('campaign-id');
        $batchSize = (int) $input->getOption('batch-size');
        $delay = (int) $input->getOption('delay');
        $testMode = $input->getOption('test');
        $forceMode = $input->getOption('force');

        // Désactiver le logging SQL pour améliorer les performances
        $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);

        try {
            $campaign = $this->entityManager->getRepository(NewsletterCampaign::class)->find($campaignId);

            if (!$campaign) {
                $io->error("Campaign #{$campaignId} not found");
                return Command::FAILURE;
            }

            $currentStatus = $campaign->getStatus();
            $allowedStatuses = ['pending', 'processing'];
            
            if (!$forceMode && !in_array($currentStatus, $allowedStatuses)) {
                if ($currentStatus === 'completed') {
                    $io->warning("Campaign #{$campaignId} is already completed");
                    return Command::SUCCESS;
                }
                
                $io->error("Campaign #{$campaignId} is not ready (status: {$currentStatus})");
                $io->note("Use --force flag to override");
                return Command::FAILURE;
            }

            // Mode test
            if ($testMode) {
                return $this->sendTest($campaign, $io);
            }

            // Mode production
            return $this->sendProduction($campaign, $batchSize, $delay, $forceMode, $io);

        } catch (\Throwable $e) {
            $io->error("Fatal error: {$e->getMessage()}");
            $io->error("File: {$e->getFile()}:{$e->getLine()}");
            $io->error("Trace: {$e->getTraceAsString()}");
            
            return Command::FAILURE;
        }
    }

    private function sendProduction(
        NewsletterCampaign $campaign,
        int $batchSize,
        int $delay,
        bool $forceMode,
        SymfonyStyle $io
    ): int {
        $io->title("Starting campaign #{$campaign->getId()}");
        
        // Les messages avec différents niveaux de verbosité
        $io->text("📧 Campaign: #{$campaign->getId()}");
        $io->text("📝 Subject: {$campaign->getSubject()}");
        $io->text("👤 Sender: {$campaign->getSenderName()} <{$campaign->getSenderEmail()}>");

        try {
            // Mettre à jour le statut
            $campaign->setStatus('processing');
            
            if (!$campaign->getStartedAt()) {
                $campaign->setStartedAt(new \DateTimeImmutable());
            }
            
            $this->entityManager->flush();

            // Récupérer les abonnés
            $subscribers = $campaign->getSubscribers();
            $totalSubscribers = $subscribers->count();

            if ($totalSubscribers === 0) {
                $io->warning("No subscribers found for this campaign");
                $campaign->setStatus('completed');
                $campaign->setCompletedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
                return Command::SUCCESS;
            }

            $io->text("👥 Recipients: {$totalSubscribers}");
            $io->text("📦 Batch size: {$batchSize}");
            $io->text("⏱️  Delay: {$delay} microseconds");
            $io->newLine();

            $progressBar = $io->createProgressBar($totalSubscribers);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
            $progressBar->setMessage('Starting...');
            $progressBar->start();

            $sentCount = 0;
            $failedCount = 0;
            $currentBatch = 0;
            $errors = [];

            foreach ($subscribers as $subscriber) {
                $progressBar->setMessage("Processing: {$subscriber->getEmail()}");
                
                try {
                    // Vérifier si l'abonné est actif
                    if (!$subscriber->isActive()) {
                        $io->writeln("\n⚠️  Inactive subscriber: {$subscriber->getEmail()}", OutputInterface::VERBOSITY_VERBOSE);
                        $failedCount++;
                        $errors[] = "{$subscriber->getEmail()} : Subscriber inactive";
                        $progressBar->advance();
                        continue;
                    }

                    // Vérifier le format de l'email
                    if (!filter_var($subscriber->getEmail(), FILTER_VALIDATE_EMAIL)) {
                        $io->writeln("\n❌ Invalid email format: {$subscriber->getEmail()}", OutputInterface::VERBOSITY_VERBOSE);
                        $failedCount++;
                        $errors[] = "{$subscriber->getEmail()} : Invalid email format";
                        $progressBar->advance();
                        continue;
                    }

                    // Générer un token de désinscription si nécessaire
                    if (!$subscriber->getUnsubscribeToken()) {
                        $subscriber->generateUnsubscribeToken();
                        $this->entityManager->persist($subscriber);
                    }

                    // Envoyer l'email (message visible en mode -v)
                    $io->writeln("\n📤 Sending to: {$subscriber->getEmail()}", OutputInterface::VERBOSITY_VERBOSE);
                    $this->sendEmail($campaign, $subscriber);
                    $io->writeln("\n✅ Sent to: {$subscriber->getEmail()}", OutputInterface::VERBOSITY_VERBOSE);
                    
                    $sentCount++;
                    
                    // Mettre à jour les compteurs
                    $campaign->setSentCount($sentCount);
                    $campaign->setFailedCount($failedCount);
                    $campaign->setLastUpdate(new \DateTimeImmutable());
                    
                    $currentBatch++;
                    if ($currentBatch >= $batchSize) {
                        $this->entityManager->flush();
                        $currentBatch = 0;
                    }
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    $errorMsg = "{$subscriber->getEmail()} : {$e->getMessage()}";
                    $errors[] = $errorMsg;
                    $io->writeln("\n❌ Error: {$errorMsg}", OutputInterface::VERBOSITY_VERBOSE);
                }

                $progressBar->advance();
                
                // Pause pour éviter le rate limiting
                if ($delay > 0) {
                    usleep($delay);
                }
            }

            $progressBar->finish();
            $progressBar->setMessage('Completed');
            $io->newLine(2);

            // Final flush
            $this->entityManager->flush();

            // Finaliser la campagne
            $campaign->setStatus('completed');
            $campaign->setCompletedAt(new \DateTimeImmutable());
            
            if (!empty($errors)) {
                $campaign->setError(implode("\n", array_slice($errors, 0, 10)));
                $io->warning("Some errors occurred:");
                foreach (array_slice($errors, 0, 5) as $error) {
                    $io->text("  • {$error}");
                }
                if (count($errors) > 5) {
                    $io->text("  • ... and " . (count($errors) - 5) . " more");
                }
            }
            
            $this->entityManager->flush();

            // Afficher le rapport final
            $io->success("🎉 Campaign completed successfully!");
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total recipients', $totalSubscribers],
                    ['Successfully sent', $sentCount],
                    ['Failures', $failedCount],
                    ['Success rate', $totalSubscribers > 0 ? round(($sentCount / $totalSubscribers) * 100, 2) . '%' : '0%'],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error("💥 Fatal error: {$e->getMessage()}");
            
            // Marquer la campagne comme échouée
            $campaign->setStatus('failed');
            $campaign->setError($e->getMessage());
            $this->entityManager->flush();

            return Command::FAILURE;
        }
    }

    private function sendTest(NewsletterCampaign $campaign, SymfonyStyle $io): int
    {
        $io->title("🧪 Test mode - Campaign #{$campaign->getId()}");
        
        try {
            // Créer un subscriber factice pour le test
            $testEmail = 'wbelbeche.s@gmail.com';
            
            $io->text("📧 Test email: {$testEmail}");
            $io->text("📝 Subject: [TEST] {$campaign->getSubject()}");
            $io->newLine();
            
            // Créer un objet subscriber factice
            $testSubscriber = new NewsletterSubscriber();
            $testSubscriber->setEmail($testEmail);
            $testSubscriber->setFirstName('Test');
            $testSubscriber->setUnsubscribeToken('test-token-123');
            
            // Envoyer l'email de test
            $this->sendEmail($campaign, $testSubscriber, true);
            
            $io->success("✅ Test email sent successfully to {$testEmail}");
            $io->note("Check your inbox and spam folder");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error("❌ Test failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function sendEmail(
        NewsletterCampaign $campaign, 
        NewsletterSubscriber $subscriber, 
        bool $testMode = false
    ): void {
        $unsubscribeUrl = $this->generateUnsubscribeUrl($subscriber);
        
        // Préparer le sujet (ajouter [TEST] en mode test)
        $subject = $testMode ? '[TEST] ' . $campaign->getSubject() : $campaign->getSubject();
        
        // Créer l'email avec template Twig
        $email = (new TemplatedEmail())
            ->from(new Address($campaign->getSenderEmail(), $campaign->getSenderName()))
            ->to($subscriber->getEmail())
            ->subject($subject)
            ->htmlTemplate('emails/newsletter.html.twig')
            ->context([
                'campaign' => $campaign,
                'subscriber' => $subscriber,
                'unsubscribeUrl' => $unsubscribeUrl,
                'testMode' => $testMode,
            ]);

        // Ajouter le texte de prévisualisation comme alternative texte
        if ($campaign->getPreviewText()) {
            $email->text($campaign->getPreviewText());
        } else {
            // Générer un texte alternatif depuis le HTML
            $textContent = strip_tags($campaign->getContent());
            if (strlen($textContent) > 500) {
                $textContent = substr($textContent, 0, 500) . '...';
            }
            $email->text($textContent);
        }

        // Envoyer l'email
        $this->mailer->send($email);
    }

    private function generateUnsubscribeUrl(NewsletterSubscriber $subscriber): string
    {
        try {
            return $this->urlGenerator->generate(
                'app_newsletter_unsubscribe',
                ['token' => $subscriber->getUnsubscribeToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } catch (\Exception $e) {
            // Fallback si la route n'existe pas
            $baseUrl = $_SERVER['APP_URL'] ?? 'http://localhost:8000';
            return $baseUrl . '/newsletter/unsubscribe/' . $subscriber->getUnsubscribeToken();
        }
    }
}