<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Surveille les pages officielles des formations pour savoir si un programme 2027 est publié, puis (avec --importer)
 * importe celles dont le fichier de mesures relu existe.
 *
 *   php bin/console app:programmes:verifier-2027                 contrôle, rien n'est écrit en base
 *   php bin/console app:programmes:verifier-2027 --importer      importe les formations dont <fichier>.json existe
 *
 * Quota : au plus --max requêtes par passage (30 par défaut), une pause entre deux requêtes, et une formation déjà
 * contrôlée il y a moins de 24 h est sautée (sauf --force). Le suivi est dans var/programmes_verif.json, donc le
 * passage est reprenable : à lancer chaque jour par cron, il avance sans jamais dépasser le quota.
 *
 * Le contrôle est un indice, pas une décision : "2027" présent sans "2022" = programme probable ; "2022" seul = ancien
 * programme ; rien = non trouvé. Un humain relit toujours la page avant de préparer le fichier de mesures. Les mesures
 * importées arrivent masquées et se publient depuis /admin/a-valider. Aucun site tiers n'est lu.
 */
#[AsCommand(name: 'app:programmes:verifier-2027', description: 'Vérifie si les pages officielles des formations publient un programme 2027, et importe ce qui est prêt')]
class ProgrammesVerifier2027Command extends Command
{
    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire('%kernel.project_dir%')] private readonly string $root,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('max', null, InputOption::VALUE_REQUIRED, 'Requêtes maximum par passage', '30')
            ->addOption('pause', null, InputOption::VALUE_REQUIRED, 'Pause entre deux requêtes (secondes)', '2')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Recontrôler même si fait il y a moins de 24 h')
            ->addOption('importer', null, InputOption::VALUE_NONE, 'Importer les formations dont le fichier de mesures existe')
            ->addOption('dossier', null, InputOption::VALUE_REQUIRED, 'Dossier des fichiers', 'docs-publics/programmes2027');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $dir = $this->root . '/' . trim((string) $in->getOption('dossier'), '/');
        $conf = json_decode((string) @file_get_contents($dir . '/sources.json'), true);
        if (!is_array($conf['partis'] ?? null)) {
            $out->writeln('<error>sources.json illisible.</error>');

            return Command::FAILURE;
        }
        $statePath = $this->root . '/var/programmes_verif.json';
        $state = json_decode((string) @file_get_contents($statePath), true) ?: [];
        $budget = max(1, (int) $in->getOption('max'));
        $pause = max(0, (int) $in->getOption('pause'));
        $rows = [];
        foreach ($conf['partis'] as $p) {
            $code = (string) $p['code'];
            $fresh = isset($state[$code]['at']) && $state[$code]['at'] > time() - 86400;
            if ($fresh && !$in->getOption('force')) {
                $rows[] = $this->row($p, $state[$code], $dir, '(déjà contrôlé)');
                continue;
            }
            if ($budget < count($p['pages'])) {
                $rows[] = [$code, $p['nom'], 'quota atteint, au prochain passage', '', ''];
                continue;
            }
            $r = ['at' => time(), 'status' => 'introuvable', 'pages' => []];
            foreach ($p['pages'] as $url) {
                --$budget;
                $r['pages'][$url] = $this->probe((string) $url);
                if ($pause) {
                    sleep($pause);
                }
            }
            $r['status'] = self::verdict($r['pages']);
            $state[$code] = $r;
            $rows[] = $this->row($p, $r, $dir, '');
        }
        @file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        (new Table($out))->setHeaders(['Sigle', 'Formation', 'Verdict', 'Mesures relues', 'Note'])->setRows($rows)->render();

        if ($in->getOption('importer')) {
            $app = $this->getApplication();
            foreach ($conf['partis'] as $p) {
                $file = $dir . '/' . $p['fichier'] . '.json';
                if (!is_file($file)) {
                    continue;
                }
                $out->writeln('Import de ' . $p['code'] . ' :');
                $app?->find('app:programmes:import-2027')->run(new \Symfony\Component\Console\Input\ArrayInput(['fichier' => $file]), $out);
            }
        }

        return Command::SUCCESS;
    }

    /** @param array<string,mixed> $p @param array<string,mixed> $r @return list<string> */
    private function row(array $p, array $r, string $dir, string $note): array
    {
        $labels = ['programme_2027_probable' => 'programme 2027 probable', 'ancien_programme' => 'programme 2022 seulement', 'aucun_signal' => 'aucun signal de programme 2027', 'introuvable' => 'page injoignable'];

        return [(string) $p['code'], (string) $p['nom'], $labels[$r['status']] ?? (string) $r['status'], is_file($dir . '/' . $p['fichier'] . '.json') ? 'oui' : 'non', $note];
    }

    /** @return array{http:int,has2027:bool,has2022:bool} */
    private function probe(string $url): array
    {
        if (!str_starts_with($url, 'https://')) {
            return ['http' => 0, 'has2027' => false, 'has2022' => false];
        }
        try {
            $res = $this->http->request('GET', $url, ['timeout' => 15, 'max_duration' => 30, 'headers' => ['User-Agent' => 'LaFranceEnDebat-verification/1.0 (contact via le site)']]);
            $code = $res->getStatusCode();
            $text = 200 === $code ? mb_strtolower(strip_tags(mb_substr($res->getContent(false), 0, 600000))) : '';
        } catch (\Throwable) {
            return ['http' => 0, 'has2027' => false, 'has2022' => false];
        }

        return ['http' => $code, 'has2027' => (bool) preg_match('/(programme|projet|propositions|mesures)[^.]{0,80}2027|2027[^.]{0,80}(programme|projet|propositions|mesures)/u', $text), 'has2022' => (bool) preg_match('/(programme|projet|propositions|mesures)[^.]{0,80}2022|2022[^.]{0,80}(programme|projet|propositions|mesures)/u', $text)];
    }

    /** @param array<string,array{http:int,has2027:bool,has2022:bool}> $pages */
    public static function verdict(array $pages): string
    {
        $ok = array_filter($pages, static fn (array $p): bool => 200 === $p['http']);
        if (!$ok) {
            return 'introuvable';
        }
        $has27 = (bool) array_filter($ok, static fn (array $p): bool => $p['has2027']);
        $has22 = (bool) array_filter($ok, static fn (array $p): bool => $p['has2022']);

        return $has27 ? 'programme_2027_probable' : ($has22 ? 'ancien_programme' : 'aucun_signal');
    }
}
