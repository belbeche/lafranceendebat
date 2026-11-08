<?php

namespace App\Command;

use App\Service\PublicActorImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Mise a jour nocturne des acteurs publics depuis les donnees ouvertes. N'ENVOIE RIEN :
 * elle ajoute les nouvelles fiches (les doublons sont ignores). L'envoi passe par app:lettre:envoyer.
 */
#[AsCommand(name: 'app:actors:sync', description: "Importe/actualise les acteurs publics (annuaire par departement, assemblee, senat)")]
class ActorsSyncCommand extends Command
{
    use LockableTrait;

    private const DEPARTEMENTS = [
        '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '2A', '2B', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60', '61', '62', '63', '64', '65', '66', '67', '68', '69', '70', '71', '72', '73', '74', '75', '76', '77', '78', '79', '80', '81', '82', '83', '84', '85', '86', '87', '88', '89', '90', '91', '92', '93', '94', '95', '971', '972', '973', '974', '976',
    ];

    public function __construct(private readonly PublicActorImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sources', 's', InputOption::VALUE_REQUIRED, 'Liste separee par des virgules : annuaire,assemblee,senat', 'annuaire,assemblee,senat')
            ->addOption('dep', 'd', InputOption::VALUE_REQUIRED, 'Departements de l\'annuaire (ex. 27,76 ou « tous »)', 'tous')
            ->addOption('types', 't', InputOption::VALUE_REQUIRED, 'Organismes de l\'annuaire', 'Mairie,Préfecture,Département,Région')
            ->addOption('simulation', null, InputOption::VALUE_NONE, 'Ne rien enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('Une synchronisation est deja en cours.');

            return Command::SUCCESS;
        }
        $dry = (bool) $input->getOption('simulation');
        $sources = array_intersect(array_map('trim', explode(',', (string) $input->getOption('sources'))), PublicActorImporter::SOURCES);
        $deps = 'tous' === $input->getOption('dep') ? self::DEPARTEMENTS : array_map('trim', explode(',', (string) $input->getOption('dep')));
        $total = ['read' => 0, 'added' => 0, 'refused' => 0];
        $failed = false;
        foreach ($sources as $source) {
            $runs = 'annuaire' === $source ? $this->annuaireRuns($input, $deps) : [[]];
            foreach ($runs as $opts) {
                $r = $this->importer->run($source, $dry, $opts);
                foreach (['read', 'added', 'refused'] as $k) {
                    $total[$k] += $r[$k];
                }
                if ($r['error']) {
                    $failed = true;
                    $output->writeln(sprintf('<error>%s %s : %s</error>', $source, json_encode($opts), $r['error']));
                } elseif ($r['added'] > 0 || 'annuaire' !== $source) {
                    $output->writeln(sprintf('%s %s : %d lues, %d ajoutees', $source, $opts ? ($opts['recherche'] . ' ' . $opts['departement']) : '', $r['read'], $r['added']));
                }
            }
        }
        $output->writeln(sprintf('Total : %d lues, %d ajoutees, %d refusees%s.', $total['read'], $total['added'], $total['refused'], $dry ? ' (simulation)' : ''));

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    private function annuaireRuns(InputInterface $input, array $deps): array
    {
        $runs = [];
        foreach (explode(',', (string) $input->getOption('types')) as $type) {
            foreach ($deps as $d) {
                $runs[] = ['recherche' => trim($type), 'departement' => $d, 'limite' => 9900];
            }
        }

        return $runs;
    }
}
