<?php

namespace App\Command;

use App\Service\Agenda\NationalEventImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Cron conseillé : 0 5 * * * php bin/console app:agenda:importer --env=prod  (chaque nuit à 5 h). */
#[AsCommand(name: 'app:agenda:importer', description: "Importe et vérifie les événements nationaux dans l'Agenda")]
class AgendaImporterCommand extends Command
{
    use LockableTrait;

    public function __construct(private readonly NationalEventImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation, rien n\'est écrit')->addOption('annees', null, InputOption::VALUE_REQUIRED, 'Nombre d\'années à importer', '2');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        if (!$this->lock()) {
            $out->writeln('Un import est déjà en cours.');

            return Command::SUCCESS;
        }
        $r = $this->importer->run((bool) $in->getOption('dry-run'), (int) $in->getOption('annees'));
        $out->writeln(sprintf('%d créés, %d mis à jour, %d inchangés, %d refusés, %d erreurs de source.', $r['created'], $r['updated'], $r['unchanged'], count($r['rejected']), count($r['errors'])));
        foreach ($r['rejected'] as $x) {
            $out->writeln('  refusé : ' . $x);
        }
        foreach ($r['errors'] as $x) {
            $out->writeln('  <comment>' . $x . '</comment>');
        }

        // Code 0 meme si une source est indisponible : les autres sources ont ete traitees (le cron ne doit pas alerter pour une panne passagere).
        return Command::SUCCESS;
    }
}
