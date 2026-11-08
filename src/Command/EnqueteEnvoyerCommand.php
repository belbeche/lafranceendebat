<?php

namespace App\Command;

use App\Entity\Survey;
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

/** Envoie par lots les invitations d'enquete aux abonnes confirmes de la lettre. Reprenable, sans doublon (curseur enregistre avant chaque envoi). */
#[AsCommand(name: 'app:enquete:envoyer', description: "Envoie par lots l'invitation de l'enquête en file")]
class EnqueteEnvoyerCommand extends Command
{
    use LockableTrait;

    public function __construct(private readonly EntityManagerInterface $em, private readonly LettreRepository $recipients, private readonly LettreMailer $mailer, private readonly LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('max', null, InputOption::VALUE_REQUIRED, "Envois maximum par exécution", '200');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        if (!$this->lock()) {
            return Command::SUCCESS;
        }
        $survey = $this->em->getRepository(Survey::class)->findOneBy(['sendStatus' => 'sending', 'status' => Survey::OPEN], ['id' => 'ASC']);
        if (!$survey) {
            $out->writeln('Aucune enquête en file.');

            return Command::SUCCESS;
        }
        if ($problem = $this->mailer->readinessProblem()) {
            $out->writeln('<error>' . $problem . '</error>');

            return Command::FAILURE;
        }
        $max = max(1, (int) $in->getOption('max'));
        $done = 0;
        while ($done < $max) {
            $batch = $this->recipients->nextRecipients($survey->getSendCursor(), min(25, $max - $done));
            if (!$batch) {
                $survey->finishSend();
                $this->em->flush();
                $out->writeln(sprintf('Terminé : %d invitations envoyées.', $survey->getSentCount()));

                return Command::SUCCESS;
            }
            foreach ($batch as $sub) {
                $survey->setSendCursor((int) $sub->getId());
                $this->em->flush(); // curseur enregistre AVANT l'envoi : jamais deux fois la meme personne
                try {
                    $this->mailer->sendSurvey($survey, $sub);
                    $survey->incSent();
                } catch (\Throwable $e) {
                    $this->logger->error('Echec invitation enquete', ['survey' => $survey->getId(), 'subscriber' => $sub->getId(), 'error' => $e->getMessage()]);
                }
                $this->em->flush();
                $this->em->detach($sub);
                ++$done;
                usleep(200000);
            }
        }
        $out->writeln(sprintf('Lot terminé : %d envoyés au total.', $survey->getSentCount()));

        return Command::SUCCESS;
    }
}
