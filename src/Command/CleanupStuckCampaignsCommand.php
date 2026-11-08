<?php

// src/Command/CleanupStuckCampaignsCommand.php

namespace App\Command;

use App\Repository\NewsletterCampaignRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:campaign:cleanup',
    description: 'Clean up stuck newsletter campaigns',
)]
/**
 * Commande console « CleanupStuckCampaignsCommand ».
 *
 * Lancer : php bin/console app:campaign:cleanup. Clean up stuck newsletter campaigns
 */
class CleanupStuckCampaignsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private NewsletterCampaignRepository $campaignRepo
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Nettoyage des campagnes bloquées');

        // Trouver les campagnes en "processing" depuis plus de 10 minutes
        $stuckCampaigns = $this->campaignRepo->findStuckCampaigns();

        if (empty($stuckCampaigns)) {
            $io->success('Aucune campagne bloquée trouvée');
            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Sujet', 'Débutée à', 'Durée'],
            array_map(function($campaign) {
                $duration = (new \DateTime())->getTimestamp() - $campaign->getStartedAt()->getTimestamp();
                return [
                    $campaign->getId(),
                    $campaign->getSubject(),
                    $campaign->getStartedAt()->format('d/m/Y H:i'),
                    round($duration / 60, 1) . ' minutes'
                ];
            }, $stuckCampaigns)
        );

        if ($io->confirm('Voulez-vous réinitialiser ces campagnes ?', false)) {
            foreach ($stuckCampaigns as $campaign) {
                $campaign->setStatus('pending');
                $campaign->setError('Réinitialisée automatiquement après blocage');
                $io->text("Campagne #{$campaign->getId()} réinitialisée");
            }
            
            $this->em->flush();
            $io->success(count($stuckCampaigns) . ' campagnes réinitialisées');
        }

        return Command::SUCCESS;
    }
}