<?php

namespace App\Command;

use App\Service\ContentTranslator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Complète translations/ui/<langue>.json à partir de la liste produite par app:i18n:audit --json.
 * Reprend où il s'est arrêté, protège les nombres {#n}, saute les textes trop longs (contenus saisis).
 */
#[AsCommand(name: 'app:i18n:completer', description: "Traduit automatiquement les textes d'interface manquants d'une langue (catalogue ui)")]
class I18nCompleteCommand extends Command
{
    public function __construct(private readonly ContentTranslator $translator, private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('locale', InputArgument::REQUIRED, 'Langue cible')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'JSON des textes manquants (app:i18n:audit --json)', '')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Textes maximum par exécution', '400');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loc = (string) $input->getArgument('locale');
        $file = $this->projectDir . '/translations/ui/' . $loc . '.json';
        $cat = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        $list = json_decode((string) file_get_contents((string) $input->getOption('from')), true) ?: [];
        $n = 0;
        $limit = (int) $input->getOption('limit');
        foreach ($list as $fr) {
            if (isset($cat[$fr]) || mb_strlen($fr) > 260 || $n >= $limit) {
                continue;
            }
            $safe = preg_replace('/\{#(\d+)\}/', '[[$1]]', $fr);
            try {
                $out = $this->translator->translateUi([$safe], $loc)[0] ?? '';
            } catch (\Throwable $e) {
                $output->writeln('<comment>' . $e->getMessage() . '</comment>');
                break;
            }
            $out = preg_replace('/\[\[\s*(\d+)\s*\]\]/', '{#$1}', $out);
            $out = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $out) ?? '';
            if ('' === trim($out) || substr_count($fr, '{#') !== substr_count($out, '{#')) {
                continue;
            }
            $cat[$fr] = $out;
            if (0 === ++$n % 25) {
                $this->save($file, $cat);
            }
        }
        $this->save($file, $cat);
        $output->writeln(sprintf('%s : %d texte(s) ajouté(s), %d au catalogue.', $loc, $n, count($cat)));

        return Command::SUCCESS;
    }

    private function save(string $file, array $cat): void
    {
        ksort($cat);
        file_put_contents($file, json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
