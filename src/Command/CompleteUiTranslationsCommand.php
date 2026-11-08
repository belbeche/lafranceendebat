<?php

namespace App\Command;

use App\Entity\Revendication;
use App\Service\ContentTranslator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Commande console « CompleteUiTranslationsCommand ».
 *
 * Lancer : php bin/console app:traductions:completer
 */
#[AsCommand(name: 'app:traductions:completer', description: "Complète les textes d'interface manquants (translations/messages.<langue>.yaml) à partir du français. Reprend où il s'est arrêté.")]
class CompleteUiTranslationsCommand extends Command
{
    public function __construct(private readonly ContentTranslator $translator, #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Une seule langue (ex. ja)', '')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Clés maximum par langue et par exécution', '60')
            ->addOption('liste', null, InputOption::VALUE_NONE, 'Affiche seulement le nombre de clés manquantes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->projectDir . '/translations/';
        $fr = $this->flat(Yaml::parseFile($dir . 'messages.fr.yaml'));
        $limit = max(1, (int) $input->getOption('limit'));
        foreach (Revendication::LOCALES as $loc) {
            if ('fr' === $loc || ($input->getOption('locale') && $input->getOption('locale') !== $loc)) {
                continue;
            }
            $file = $dir . "messages.$loc.yaml";
            $have = is_file($file) ? $this->flat(Yaml::parseFile($file) ?? []) : [];
            $missing = array_diff_key($fr, $have);
            $output->writeln(sprintf('%s : %d clé(s) manquante(s)', $loc, count($missing)));
            if ($input->getOption('liste') || !$missing || !$this->translator->enabled()) {
                continue;
            }
            $batch = array_slice($missing, 0, $limit, true);
            try {
                foreach ($batch as $key => $text) {
                    // les variables %nom% sont protégées pour ne pas être traduites
                    $tokens = [];
                    $safe = preg_replace_callback('/%[a-zA-Z_]+%|\{\{?[^}]+\}?\}/', function ($m) use (&$tokens) { $tokens[] = $m[0]; return '[[' . (count($tokens) - 1) . ']]'; }, (string) $text);
                    $out = $this->translator->translateUi([$safe], $loc)[0] ?? '';
                    $out = preg_replace_callback('/\[\[\s*(\d+)\s*\]\]/', fn ($m) => $tokens[(int) $m[1]] ?? $m[0], $out);
                    $out = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F-\\x9F]/u', '', $out) ?? '';
                    if ('' !== trim($out)) {
                        $have[$key] = $out;
                    }
                }
            } catch (\Throwable $e) {
                $output->writeln('<comment>' . $e->getMessage() . '</comment>');
            }
            file_put_contents($file, Yaml::dump($this->nest($have), 6, 2));
        }

        return Command::SUCCESS;
    }

    private function flat(array $a, string $p = ''): array
    {
        $o = [];
        foreach ($a as $k => $v) {
            is_array($v) ? $o += $this->flat($v, $p . $k . '.') : $o[$p . $k] = $v;
        }

        return $o;
    }

    private function nest(array $flat): array
    {
        $r = [];
        foreach ($flat as $k => $v) {
            $ref = &$r;
            foreach (explode('.', $k) as $part) {
                $ref = &$ref[$part];
            }
            $ref = $v;
            unset($ref);
        }

        return $r;
    }
}
