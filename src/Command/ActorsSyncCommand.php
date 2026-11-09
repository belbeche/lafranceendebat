<?php

namespace App\Command;

use App\Service\PublicActorImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mise a jour des acteurs publics depuis les donnees ouvertes. N'ENVOIE RIEN :
 * elle ajoute les nouvelles fiches (les doublons sont ignores). L'envoi passe par app:lettre:envoyer.
 *
 * REPRENABLE : le travail est decoupe en etapes (assemblee, senat, puis annuaire par type et departement).
 * Chaque execution travaille au plus --max-seconds (defaut 90) puis s'arrete proprement et note ou elle en est
 * (var/actors_sync_state.json). La suivante reprend au meme endroit. Une fois le cycle termine, rien ne
 * recommence avant --min-hours (defaut 20 h). On peut donc la lancer souvent : toutes les 10 minutes, la nuit.
 */
#[AsCommand(name: 'app:actors:sync', description: "Importe/actualise les acteurs publics (assemblee, senat, annuaire par departement), par lots reprenables")]
class ActorsSyncCommand extends Command
{
    use LockableTrait;

    private const DEPARTEMENTS = [
        '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '2A', '2B', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60', '61', '62', '63', '64', '65', '66', '67', '68', '69', '70', '71', '72', '73', '74', '75', '76', '77', '78', '79', '80', '81', '82', '83', '84', '85', '86', '87', '88', '89', '90', '91', '92', '93', '94', '95', '971', '972', '973', '974', '976',
    ];

    public function __construct(
        private readonly PublicActorImporter $importer,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sources', 's', InputOption::VALUE_REQUIRED, 'Liste separee par des virgules : annuaire,assemblee,senat', 'assemblee,senat,annuaire')
            ->addOption('dep', 'd', InputOption::VALUE_REQUIRED, 'Departements de l\'annuaire (ex. 27,76 ou « tous »)', 'tous')
            ->addOption('types', 't', InputOption::VALUE_REQUIRED, 'Organismes de l\'annuaire', 'Mairie,Préfecture,Département,Région')
            ->addOption('max-seconds', null, InputOption::VALUE_REQUIRED, 'Temps de travail maximal de cette execution (secondes)', '90')
            ->addOption('min-hours', null, InputOption::VALUE_REQUIRED, 'Delai minimal entre deux cycles complets (heures)', '20')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Repartir du debut (oublie la position memorisee)')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Affiche l\'avancement et s\'arrete')
            ->addOption('simulation', null, InputOption::VALUE_NONE, 'Ne rien enregistrer (ne memorise pas la position)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->lock()) {
            $output->writeln('Une synchronisation est deja en cours.');

            return Command::SUCCESS;
        }
        $dry = (bool) $input->getOption('simulation');
        $started = microtime(true);
        $budget = max(10, (int) $input->getOption('max-seconds'));
        $deadline = $started + $budget;
        $sources = array_values(array_intersect(array_map('trim', explode(',', (string) $input->getOption('sources'))), PublicActorImporter::SOURCES));
        // l'ordre canonique : les sources uniques d'abord, l'annuaire (long) ensuite
        usort($sources, static fn ($a, $b) => array_search($a, ['assemblee', 'senat', 'annuaire'], true) <=> array_search($b, ['assemblee', 'senat', 'annuaire'], true));
        $deps = 'tous' === $input->getOption('dep') ? self::DEPARTEMENTS : array_map('trim', explode(',', (string) $input->getOption('dep')));
        $steps = $this->steps($sources, $deps, (string) $input->getOption('types'));
        $signature = md5(json_encode([$sources, $deps, $input->getOption('types')]));

        $state = $this->load();
        if ($input->getOption('reset') || ($state['signature'] ?? null) !== $signature) {
            $state = ['signature' => $signature, 'pos' => 0, 'offset' => 0, 'done_at' => $state['done_at'] ?? null, 'cycle_started' => null, 'totals' => ['read' => 0, 'added' => 0, 'refused' => 0]];
            if ($input->getOption('reset')) {
                $state['done_at'] = null;
            }
        }
        if ($input->getOption('status')) {
            $output->writeln(sprintf('Etape %d / %d, derniere fin de cycle : %s, cumul du cycle : %s', $state['pos'], count($steps), $state['done_at'] ?? 'jamais', json_encode($state['totals'])));

            return Command::SUCCESS;
        }
        // cycle termine recemment : rien a faire avant le delai minimal
        if (!$dry && $state['pos'] >= count($steps) && $state['done_at']) {
            $age = time() - strtotime($state['done_at']);
            if ($age < (int) $input->getOption('min-hours') * 3600) {
                $output->writeln(sprintf('Cycle termine il y a %d min : rien a faire.', intdiv($age, 60)));

                return Command::SUCCESS;
            }
            $state['pos'] = 0;
            $state['offset'] = 0;
            $state['totals'] = ['read' => 0, 'added' => 0, 'refused' => 0];
        }
        $state['cycle_started'] ??= date('c');

        $failed = false;
        $done = 0;
        $runTotal = ['read' => 0, 'added' => 0, 'refused' => 0];
        while ($state['pos'] < count($steps)) {
            if (microtime(true) >= $deadline) {
                break;
            }
            [$source, $opts] = $steps[$state['pos']];
            if ('annuaire' === $source) {
                $opts['offset'] = $state['offset'];
                $opts['deadline'] = $deadline;
            }
            $r = $this->importer->run($source, $dry, $opts);
            foreach (['read', 'added', 'refused'] as $k) {
                $runTotal[$k] += $r[$k];
                $state['totals'][$k] += $r[$k];
            }
            if ($r['error']) {
                $failed = true;
                $output->writeln(sprintf('<error>%s %s : %s</error>', $source, json_encode(array_diff_key($opts, ['deadline' => 1])), $r['error']));
            } elseif ($r['added'] > 0 || 'annuaire' !== $source) {
                $output->writeln(sprintf('%s %s : %d lues, %d ajoutees', $source, $opts ? ($opts['recherche'] . ' ' . $opts['departement']) : '', $r['read'], $r['added']));
            }
            if (null !== $this->importer->nextOffset && !$r['error']) {
                $state['offset'] = $this->importer->nextOffset; // temps epuise au milieu de cette etape
                break;
            }
            // une etape en erreur est sautee (elle sera retentee au prochain cycle) pour ne pas bloquer toute la suite
            ++$state['pos'];
            $state['offset'] = 0;
            ++$done;
            if (!$dry) {
                $this->save($state);
            }
        }
        $complete = $state['pos'] >= count($steps);
        if ($complete && !$dry) {
            $state['done_at'] = date('c');
        }
        if (!$dry) {
            $this->save($state);
        }
        $output->writeln(sprintf(
            'Cette execution : %d etape(s) en %.0f s, %d lues, %d ajoutees, %d refusees%s. Avancement : %d / %d%s.',
            $done, microtime(true) - $started, $runTotal['read'], $runTotal['added'], $runTotal['refused'], $dry ? ' (simulation)' : '',
            $state['pos'], count($steps), $complete ? ', cycle TERMINE' : ', reprise a la prochaine execution'
        ));

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /** @return list<array{0:string,1:array}> */
    private function steps(array $sources, array $deps, string $types): array
    {
        $steps = [];
        foreach ($sources as $source) {
            if ('annuaire' !== $source) {
                $steps[] = [$source, []];
                continue;
            }
            foreach (explode(',', $types) as $type) {
                foreach ($deps as $d) {
                    $steps[] = ['annuaire', ['recherche' => trim($type), 'departement' => $d, 'limite' => 9900]];
                }
            }
        }

        return $steps;
    }

    private function file(): string
    {
        return $this->projectDir . '/var/actors_sync_state.json';
    }

    private function load(): array
    {
        $j = is_file($this->file()) ? json_decode((string) file_get_contents($this->file()), true) : null;

        return is_array($j) ? $j : [];
    }

    private function save(array $state): void
    {
        @file_put_contents($this->file(), json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
