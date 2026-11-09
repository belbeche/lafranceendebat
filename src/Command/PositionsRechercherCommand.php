<?php

namespace App\Command;

use App\Entity\PositionStatement;
use App\Service\Positions\PositionResearchRunner;
use App\Service\Positions\StanceResearcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lance la recherche sourcée des positions des candidats (Gemini avec recherche web). Les résultats sont
 * enregistrés en attente de validation : rien n'est public avant un modérateur. Les règles de contrôle des
 * sources sont dans StanceResearcher.
 */
#[AsCommand(name: 'app:positions:rechercher', description: 'Recherche sourcée (Gemini + recherche web) des positions des candidats ; rien n\'est public avant validation')]
class PositionsRechercherCommand extends Command
{
    public function __construct(private readonly PositionResearchRunner $runner, private readonly StanceResearcher $researcher, private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('affirmation', null, InputOption::VALUE_REQUIRED, 'Identifiant de l\'affirmation (sinon toutes)')->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Personnes par affirmation', '10')->addOption('max-seconds', null, InputOption::VALUE_REQUIRED, 'Durée maximale', '90');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        if (!$this->researcher->isEnabled()) {
            $out->writeln('GEMINI_API_KEY absente : rien à faire.');

            return Command::SUCCESS;
        }
        $id = $in->getOption('affirmation');
        $list = $id ? array_filter([$this->em->find(PositionStatement::class, (int) $id)]) : $this->em->getRepository(PositionStatement::class)->findAll();
        $end = time() + (int) $in->getOption('max-seconds');
        foreach ($list as $s) {
            if (time() >= $end) {
                break;
            }
            $r = $this->runner->run($s, (int) $in->getOption('limit'), max(5, $end - time()));
            $out->writeln(sprintf('#%d : %d proposée(s), %d sans position, %d écartée(s), %d restante(s)', $s->getId(), $r['added'], $r['none'], count($r['errors']), $r['remaining']));
        }

        return Command::SUCCESS;
    }
}
