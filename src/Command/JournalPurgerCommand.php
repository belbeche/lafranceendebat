<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Supprime le journal de securite (12 mois), les lectures et preuves de consentement (13 mois) (a planifier chaque nuit). */
#[AsCommand(name: 'app:journal:purger', description: 'Supprime le journal de sécurité de plus de 12 mois')]
class JournalPurgerCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly \App\Service\Audience\ViewTracker $views)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $n = $this->em->createQuery('DELETE FROM App\Entity\UserEvent e WHERE e.at < :d')->setParameter('d', new \DateTimeImmutable('-12 months'))->execute();
        $this->em->createQuery('DELETE FROM App\Entity\ModerationIncident m WHERE m.createdAt < :d')->setParameter('d', new \DateTimeImmutable('-12 months'))->execute();
        $v = $this->views->purge();
        $c = $this->em->createQuery('DELETE FROM App\Entity\ConsentRecord r WHERE r.at < :d')->setParameter('d', new \DateTimeImmutable('-13 months'))->execute();
        $out->writeln($n . ' ligne(s) de journal, ' . $v . ' lecture(s) et ' . $c . ' preuve(s) de consentement supprimée(s).');

        return Command::SUCCESS;
    }
}
