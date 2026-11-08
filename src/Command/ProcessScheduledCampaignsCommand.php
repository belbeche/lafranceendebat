<?php

// src/Command/ProcessScheduledCampaignsCommand.php

namespace App\Command;

use App\Entity\NewsletterCampaign;
use App\Message\SendNewsletterCampaignMessage;
use App\Repository\NewsletterCampaignRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:newsletter:process-scheduled',
    description: 'Traite les campagnes newsletter planifiées',
)]
/**
 * Commande console « ProcessScheduledCampaignsCommand ».
 *
 * Lancer : php bin/console app:newsletter:process-scheduled. Traite les campagnes newsletter planifiées
 */
class ProcessScheduledCampaignsCommand extends Command
{
    public function __construct(
        private NewsletterCampaignRepository $campaignRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Démarre les campagnes planifiées dont l\'heure est arrivée')
            ->setHelp('Cette commande vérifie et démarre automatiquement les campagnes dont la date planifiée est passée');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Traitement des campagnes planifiées');
        
        // Récupérer les campagnes en attente qui doivent démarrer
        $campaigns = $this->campaignRepository->findScheduledCampaigns();
        
        $io->text(sprintf('Trouvé %d campagne(s) planifiée(s)', count($campaigns)));
        
        $startedCount = 0;
        
        foreach ($campaigns as $campaign) {
            if ($campaign->shouldStartNow()) {
                try {
                    // Démarrer la campagne
                    $campaign->setStatus('processing');
                    $campaign->setStartedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
                    
                    $this->entityManager->persist($campaign);
                    $this->entityManager->flush();
                    
                    // Démarrer l'envoi en arrière-plan
                    $this->messageBus->dispatch(new SendNewsletterCampaignMessage($campaign->getId()));
                    
                    $io->success(sprintf(
                        'Campagne #%d démarrée : %s',
                        $campaign->getId(),
                        $campaign->getSubject()
                    ));
                    
                    $startedCount++;
                } catch (\Exception $e) {
                    $campaign->setStatus('failed');
                    $campaign->setError($e->getMessage());
                    $this->entityManager->flush();
                    
                    $io->error(sprintf(
                        'Erreur avec la campagne #%d : %s',
                        $campaign->getId(),
                        $e->getMessage()
                    ));
                }
            }
        }
        
        if ($startedCount > 0) {
            $io->success(sprintf('%d campagne(s) démarrée(s) avec succès', $startedCount));
        } else {
            $io->note('Aucune campagne à démarrer pour le moment');
        }
        
        return Command::SUCCESS;
    }
}