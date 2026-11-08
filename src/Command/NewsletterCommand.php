<?php

// src/Command/NewsletterCommand.php

namespace App\Command;

use App\Message\SendNewsletterCampaignMessage;
use App\Repository\NewsletterCampaignRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Commande console « NewsletterCommand ».
 */
class NewsletterCommand extends Command
{
    protected static $defaultName = 'app:newsletter:manage';

    private MessageBusInterface $messageBus;
    private EntityManagerInterface $entityManager;
    private NewsletterCampaignRepository $campaignRepository;

    public function __construct(
        MessageBusInterface $messageBus,
        EntityManagerInterface $entityManager,
        NewsletterCampaignRepository $campaignRepository
    ) {
        parent::__construct();
        $this->messageBus = $messageBus;
        $this->entityManager = $entityManager;
        $this->campaignRepository = $campaignRepository;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Gestion des campagnes newsletter')
            ->addArgument('action', InputArgument::REQUIRED, 'start|reset|status')
            ->addArgument('campaign-id', InputArgument::OPTIONAL, 'ID de la campagne')
            ->setHelp('
start <id>    : Démarrer une campagne
reset <id>    : Réinitialiser une campagne
reset-all     : Réinitialiser toutes les campagnes bloquées
status        : Afficher le statut des campagnes
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $campaignId = $input->getArgument('campaign-id');

        switch ($action) {
            case 'start':
                return $this->startCampaign($io, $campaignId);
            case 'reset':
                return $this->resetCampaign($io, $campaignId);
            case 'reset-all':
                return $this->resetAllStuckCampaigns($io);
            case 'status':
                return $this->showStatus($io);
            default:
                $io->error('Action inconnue. Utilisez: start, reset, reset-all, status');
                return Command::FAILURE;
        }
    }

    private function startCampaign(SymfonyStyle $io, $campaignId): int
    {
        if (!$campaignId) {
            $io->error('ID de campagne requis');
            return Command::FAILURE;
        }

        $campaign = $this->campaignRepository->find($campaignId);

        if (!$campaign) {
            $io->error("Campagne #{$campaignId} non trouvée");
            return Command::FAILURE;
        }

        // Réinitialiser si nécessaire
        if ($campaign->getStatus() === 'processing') {
            $io->warning("Campagne #{$campaignId} déjà en cours. Réinitialisation...");
            $this->resetCampaign($io, $campaignId);
        }

        // Préparer la campagne
        $campaign->setStatus('processing');
        $campaign->setStartedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
        $campaign->setSentCount(0);
        $campaign->setFailedCount(0);
        $campaign->setError(null);

        $this->entityManager->flush();

        // Envoyer le message
        $this->messageBus->dispatch(new SendNewsletterCampaignMessage($campaignId));

        $io->success("Campagne #{$campaignId} démarrée : {$campaign->getSubject()}");

        return Command::SUCCESS;
    }

    private function resetCampaign(SymfonyStyle $io, $campaignId): int
    {
        $campaign = $this->campaignRepository->find($campaignId);

        if (!$campaign) {
            $io->error("Campagne #{$campaignId} non trouvée");
            return Command::FAILURE;
        }

        $campaign->setStatus('pending');
        $campaign->setStartedAt(null);
        $campaign->setCompletedAt(null);
        $campaign->setSentCount(0);
        $campaign->setFailedCount(0);
        $campaign->setError(null);

        $this->entityManager->flush();

        $io->success("Campagne #{$campaignId} réinitialisée");

        return Command::SUCCESS;
    }

    private function resetAllStuckCampaigns(SymfonyStyle $io): int
    {
        $cutoff = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $cutoff->modify('-30 minutes');

        $stuckCampaigns = $this->campaignRepository->createQueryBuilder('c')
            ->where('c.status = :status AND c.startedAt < :cutoff')
            ->setParameter('status', 'processing')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        if (empty($stuckCampaigns)) {
            $io->success('Aucune campagne bloquée trouvée');
            return Command::SUCCESS;
        }

        foreach ($stuckCampaigns as $campaign) {
            $campaign->setStatus('failed');
            $campaign->setError('Campagne bloquée - réinitialisée automatiquement');
            $campaign->setCompletedAt(new \DateTime('now', new \DateTimeZone('Europe/Paris')));

            $io->writeln(" - Campagne #{$campaign->getId()}: {$campaign->getSubject()}");
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d campagne(s) réinitialisée(s)', count($stuckCampaigns)));

        return Command::SUCCESS;
    }

    private function showStatus(SymfonyStyle $io): int
    {
        $campaigns = $this->campaignRepository->findBy([], ['createdAt' => 'DESC']);

        $rows = [];
        foreach ($campaigns as $campaign) {
            $rows[] = [
                $campaign->getId(),
                substr($campaign->getSubject(), 0, 30) . (strlen($campaign->getSubject()) > 30 ? '...' : ''),
                $campaign->getStatus(),
                $campaign->getTotalRecipients(),
                $campaign->getSentCount(),
                $campaign->getFailedCount(),
                $campaign->getStartedAt() ? $campaign->getStartedAt()->format('d/m H:i') : '-',
                $campaign->getError() ? 'Oui' : 'Non',
            ];
        }

        $io->table(
            ['ID', 'Sujet', 'Statut', 'Dest.', 'Envoyés', 'Échecs', 'Début', 'Erreur'],
            $rows
        );

        return Command::SUCCESS;
    }
}