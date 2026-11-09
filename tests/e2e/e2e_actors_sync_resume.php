<?php
// Reprise de app:actors:sync apres arret sur budget de temps. Lancer : php tests/e2e/e2e_actors_sync_resume.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use App\Command\ActorsSyncCommand;
use App\Service\PublicActorImporter;
use Symfony\Component\Console\Tester\CommandTester;
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$dir = sys_get_temp_dir() . '/sync' . uniqid(); mkdir($dir . '/var', 0777, true);
$fake = new class() extends PublicActorImporter {
    public array $calls = []; public int $delay = 0;
    public function __construct() {}
    public function run(string $source, bool $dry = false, array $opts = []): array {
        $this->calls[] = $source . ':' . ($opts['recherche'] ?? '') . ':' . ($opts['departement'] ?? '');
        $this->nextOffset = null; if ($this->delay) { usleep($this->delay); }
        return ['read' => 1, 'added' => 1, 'skipped' => 0, 'refused' => 0, 'reasons' => [], 'error' => null];
    }
};
$mk = fn () => new CommandTester(new ActorsSyncCommand($fake, $dir));
$args = ['--dep' => '27,76', '--types' => 'Mairie'];
// 1) budget minimal (10 s) mais chaque etape dure 4 s => 3 etapes max (assemblee, senat, 27) puis arret
$fake->delay = 4000000; $c = $mk(); $c->execute($args + ['--max-seconds' => 10]);
t('arret sur budget : 3 etapes', count($fake->calls) === 3);
t('etat memorise', json_decode(file_get_contents($dir . '/var/actors_sync_state.json'), true)['pos'] === 3);
// 2) reprise : la suivante termine avec l'etape restante seulement
$fake->calls = []; $fake->delay = 0; $c = $mk(); $c->execute($args + ['--max-seconds' => 10]);
t('reprise : une seule etape restante (76)', $fake->calls === ['annuaire:Mairie:76']);
t('cycle termine annonce', str_contains($c->getDisplay(), 'cycle TERMINE'));
// 3) relance immediate : rien a faire
$fake->calls = []; $c = $mk(); $c->execute($args);
t('relance immediate : rien a faire', $fake->calls === [] && str_contains($c->getDisplay(), 'rien a faire'));
// 4) --reset : repart de zero
$c = $mk(); $c->execute($args + ['--reset' => true]);
t('reset : tout rejoue', count($fake->calls) === 4);
// 5) simulation n'ecrit pas la position
@unlink($dir . '/var/actors_sync_state.json'); $fake->calls = []; $c = $mk(); $c->execute($args + ['--simulation' => true]);
t('simulation : aucun fichier d\'etat', !is_file($dir . '/var/actors_sync_state.json'));
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
