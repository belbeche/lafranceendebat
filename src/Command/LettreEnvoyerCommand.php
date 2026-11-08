<?php

namespace App\Command;

use App\Entity\Lettre;
use App\Repository\LettreRepository;
use App\Service\Lettre\LettreMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande console « LettreEnvoyerCommand ».
 *
 * Lancer : php bin/console app:lettre:envoyer
 */
#[AsCommand(name: 'app:lettre:envoyer', description: "Envoie par lots la prochaine lettre en file (reprenable, sans doublon).")]
class LettreEnvoyerCommand extends Command
{
    use LockableTrait;

    public function __construct(
        private readonly LettreRepository $lettres,
        private readonly LettreMailer $mailer,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('max', null, InputOption::VALUE_REQUIRED, "Nombre maximum d'envois par exécution", '200')
            ->addOption('delay-ms', null, InputOption::VALUE_REQUIRED, 'Pause entre deux messages (millisecondes)', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Un seul processus d'envoi a la fois : jamais de doublon.
        if (!$this->lock()) {
            $output->writeln('Un envoi est déjà en cours.');

            return Command::SUCCESS;
        }
        try {
            $lettre = $this->lettres->findNextToSend();
            if (null === $lettre) {
                $output->writeln("Aucune lettre en file.");

                return Command::SUCCESS;
            }
            if ($problem = $this->mailer->readinessProblem()) {
                $output->writeln('<error>' . $problem . '</error>');

                return Command::FAILURE;
            }
            $lettre->setStatus(Lettre::STATUS_SENDING);
            $this->em->flush();

            $max = max(1, (int) $input->getOption('max'));
            if ($lettre->isForActors()) {
                $max = min($max, 100); // demarchage : jamais plus de 100 messages par execution, pour proteger la reputation d'envoi
            }
            $delay = max(0, (int) $input->getOption('delay-ms')) * 1000;
            $done = 0;
            $skipped = 0;
            while ($done < $max) {
                $this->em->refresh($lettre);
                if (Lettre::STATUS_SENDING !== $lettre->getStatus()) {
                    $output->writeln('Envoi interrompu (annulé).');

                    return Command::SUCCESS;
                }
                $batch = $this->lettres->nextFor($lettre, $lettre->getCursorId(), min(25, $max - $done));
                if (!$batch) {
                    $lettre->setStatus(Lettre::STATUS_SENT)->setFinishedAt(new \DateTimeImmutable());
                    $this->em->flush();
                    $output->writeln(sprintf('Terminé : %d envoyés, %d échecs, %d ignorés (organisation déjà contactée).', $lettre->getSentCount(), $lettre->getFailedCount(), $skipped));

                    return Command::SUCCESS;
                }
                foreach ($batch as $sub) {
                    try {
                        if ($sub instanceof \App\Entity\ActorContact) {
                            // Une organisation ne reçoit qu'un seul message, même si elle a plusieurs adresses.
                            if ($this->em->getRepository(\App\Entity\ActorContact::class)->organisationAlreadyReached($sub, $lettre->isInvitation())) {
                                $this->logger->info('Envoi ignoré : organisation déjà contactée', ['lettre' => $lettre->getId(), 'actor' => $sub->getId()]);
                                $lettre->setCursorId((int) $sub->getId());
                                $this->em->flush();
                                $this->em->detach($sub);
                                ++$done;
                                ++$skipped;
                                continue;
                            }
                            // Marquer AVANT d'envoyer : si le processus s'arrête entre l'envoi et l'enregistrement, aucun doublon au prochain passage.
                            $prev = [$sub->getLastContactedAt(), $sub->getContactCount(), $sub->getInvitedAt()];
                            $sub->markContacted();
                            if ($lettre->isInvitation()) {
                                $sub->markInvited();
                            }
                            $this->em->flush();
                            try {
                                $this->mailer->sendToActor($lettre, $sub);
                            } catch (\Throwable $sendError) {
                                $sub->restoreContact(...$prev);
                                $this->em->flush();
                                throw $sendError;
                            }
                        } else {
                            $this->mailer->sendLettre($lettre, $sub);
                        }
                        $lettre->setSentCount($lettre->getSentCount() + 1);
                    } catch (\Throwable $e) {
                        $lettre->setFailedCount($lettre->getFailedCount() + 1);
                        $this->logger->error('Echec envoi lettre', ['lettre' => $lettre->getId(), 'subscriber' => $sub->getId(), 'error' => $e->getMessage()]);
                    }
                    $lettre->setCursorId((int) $sub->getId());
                    $this->em->flush();
                    $this->em->detach($sub);
                    ++$done;
                    usleep($delay);
                }
                $this->em->flush();
                
            }
            $output->writeln(sprintf('Lot terminé (%d messages). Relancez la commande pour continuer.', $done));

            return Command::SUCCESS;
        } finally {
            $this->release();
        }
    }
}
