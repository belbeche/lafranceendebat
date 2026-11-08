<?php

namespace App\Command;

use App\Service\HtmlTranslator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Audit de traduction : liste tout texte francais (gabarits et pages rendues) absent du catalogue d'une langue.
 * Code de sortie 1 s'il en manque : utilisable en controle avant mise en ligne.
 */
#[AsCommand(name: 'app:i18n:audit', description: 'Liste les textes non traduits pour une langue')]
class I18nAuditCommand extends Command
{
    private const EXCLUDED = ['admin', '_archive', 'emails', 'newsletter', 'webhook', 'blocked_ips', 'booking', 'conference_web_socket', 'debug', 'form', 'reset_password/email.html.twig', 'empty.html.twig', 'empty.json.twig'];

    public function __construct(private readonly HtmlTranslator $translator, private readonly KernelInterface $kernel, private readonly RouterInterface $router, private readonly string $projectDir, private readonly \App\Service\Languages $languages)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('locale', InputArgument::OPTIONAL, 'Langue a auditer', 'en')
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'Ecrit la liste des textes manquants dans ce fichier JSON')
            ->addOption('no-render', null, InputOption::VALUE_NONE, 'Ne pas parcourir les pages rendues');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = (string) $input->getArgument('locale');
        $catalogue = $this->translator->catalogue($locale);
        foreach ($this->languages->all() as $l) {
            $catalogue[$l['name']] = $l['name'];
        }
        $catalogue['La France en Débat'] = 'La France en Débat';

        $missing = [];
        foreach ($this->templateFragments() as $key => $files) {
            if (!isset($catalogue[$key])) {
                $missing[$key] = ['source' => 'gabarit', 'where' => $files[0]];
            }
        }
        if (!$input->getOption('no-render')) {
            foreach ($this->publicUrls() as $url) {
                $request = Request::create($url, 'GET', [], ['lfed_lang' => 'fr']);
                $request->setSession(new Session(new MockArraySessionStorage()));
                try {
                    $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);
                } catch (\Throwable) {
                    continue;
                }
                if ($response->getStatusCode() !== 200 || !str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                    continue;
                }
                foreach ($this->translator->fragments((string) $response->getContent()) as $key) {
                    if (!isset($catalogue[$key]) && !isset($missing[$key])) {
                        $missing[$key] = ['source' => 'page rendue', 'where' => $url];
                    }
                }
            }
        }

        ksort($missing);
        if ($path = $input->getOption('json')) {
            file_put_contents($path, json_encode(array_keys($missing), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        if (!$missing) {
            $io->success(sprintf('Langue "%s" : %d textes au catalogue, aucun texte manquant.', $locale, count($catalogue)));

            return Command::SUCCESS;
        }
        $rows = [];
        foreach ($missing as $text => $info) {
            $rows[] = [mb_substr($text, 0, 90), $info['source'], $info['where']];
        }
        $io->table(['Texte non traduit', 'Origine', 'Ou'], $rows);
        $io->warning(sprintf('%d texte(s) manquant(s) pour "%s". Les textes saisis par les utilisateurs (titres, messages) sont normaux dans la liste.', count($missing), $locale));

        return Command::FAILURE;
    }

    /** @return array<string, string[]> */
    private function templateFragments(): array
    {
        $found = [];
        $base = $this->projectDir . '/templates';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
            if (!str_ends_with($rel, '.twig')) {
                continue;
            }
            foreach (self::EXCLUDED as $ex) {
                if ($rel === $ex || str_starts_with($rel, $ex . '/')) {
                    continue 2;
                }
            }
            $s = (string) file_get_contents($file->getPathname());
            $s = preg_replace(['~\{#.*?#\}~s', '~\{%.*?%\}~s', '~<script\b.*?</script>|<style\b.*?</style>~is'], "\0", $s);
            preg_match_all('~\b(?:title|alt|placeholder|aria-label|aria-description)="([^"{}]*)"~', $s, $attrs);
            foreach ($attrs[1] as $a) {
                if (($k = HtmlTranslator::key($a)) !== null) {
                    $found[$k[0]][] = $rel;
                }
            }
            $s = preg_replace(['~\{\{.*?\}\}~s', '~<[^>]+>~s'], "\0", $s);
            foreach (explode("\0", (string) $s) as $seg) {
                foreach (explode(' | ', $seg) as $piece) {
                    if (($k = HtmlTranslator::key($piece)) !== null) {
                        $found[$k[0]][] = $rel;
                    }
                }
            }
        }

        return $found;
    }

    /** @return string[] */
    private function publicUrls(): array
    {
        $urls = [];
        foreach ($this->router->getRouteCollection() as $name => $route) {
            $path = $route->getPath();
            if (!str_starts_with($name, 'app_') || str_contains($path, '{') || !in_array('GET', $route->getMethods() ?: ['GET'], true)) {
                continue;
            }
            if (preg_match('~^/(admin|profil|propositions/(nouvelle|mes-propositions)|communaute/nouveau|logout|langue|newsletter|reset-password)~', $path)) {
                continue;
            }
            $urls[] = $path;
        }

        return $urls;
    }
}
