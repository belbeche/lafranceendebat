<?php

// src/Command/TestEmailCommand.php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoie un e-mail de test pour vérifier la configuration d'envoi (transport, expéditeur). Outil de
 * diagnostic, sans effet sur les données.
 */
class TestEmailCommand extends Command
{
    protected static $defaultName = 'app:test-email';
    
    private MailerInterface $mailer;
    
    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }
    
    protected function configure(): void
    {
        $this->setDescription('Test email sending');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $email = (new Email())
                ->from('contact@labouffonne.eu')
                ->to('wbelbeche.s@gmail.com')
                ->subject('Test Email')
                ->text('This is a test email from Symfony.')
                ->html('<p>This is a test email from Symfony.</p>');
            
            $this->mailer->send($email);
            
            $output->writeln('✅ Email sent successfully!');
            
        } catch (\Exception $e) {
            $output->writeln('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}