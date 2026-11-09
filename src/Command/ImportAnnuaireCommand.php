<?php

namespace App\Command;

use App\Service\PublicActorImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Importe des contacts publics depuis les donnees ouvertes (Annuaire de l'administration, Assemblee, Senat). */
#[AsCommand(name: 'app:import:annuaire', description: "Importe des contacts publics (annuaire, assemblee, senat)")]
class ImportAnnuaireCommand extends Command
{
    public function __construct(private readonly PublicActorImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'annuaire, assemblee ou senat', 'annuaire')
            ->addOption('recherche', 'r', InputOption::VALUE_REQUIRED, 'Annuaire : mot du nom (Mairie, Prefecture...)', 'Mairie')
            ->addOption('departement', 'd', InputOption::VALUE_REQUIRED, 'Annuaire : code departement')
            ->addOption('limite', 'l', InputOption::VALUE_REQUIRED, 'Annuaire : fiches lues au maximum', '500')
            ->addOption('simulation', null, InputOption::VALUE_NONE, 'Ne rien enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = (string) $input->getOption('source');
        if (!in_array($source, PublicActorImporter::SOURCES, true)) {
            $output->writeln('<error>Source inconnue.</error>');

            return Command::INVALID;
        }
        $r = $this->importer->run($source, (bool) $input->getOption('simulation'), ['recherche' => $input->getOption('recherche'), 'departement' => $input->getOption('departement'), 'limite' => $input->getOption('limite')]);
        if ($r['error']) {
            $output->writeln('<error>' . $r['error'] . '</error>');
        }
        $output->writeln(sprintf('Fiches lues : %d, ajoutees : %d, deja connues : %d, refusees : %d%s', $r['read'], $r['added'], $r['skipped'], $r['refused'], $input->getOption('simulation') ? ' (simulation)' : ''));
        foreach ($r['reasons'] as $why => $n) {
            $output->writeln(" - $n x $why");
        }

        return $r['error'] ? Command::FAILURE : Command::SUCCESS;
    }
}
