<?php

namespace App\Command;

use App\Controller\DossierController;
use App\Entity\Dossier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;

/**
 * Copie les dossiers éditoriaux écrits en Twig (petition, education, histoire-guerres...) dans la base,
 * pour qu'ils deviennent modifiables depuis l'administration sans toucher au code.
 * Le gabarit est rendu, puis son HTML est converti dans la mise en forme simple de SafeMarkdown.
 * Les liens internes (path()) deviennent des chemins du site, les liens https alimentent la liste des sources.
 * Sans --forcer, un dossier déjà présent en base n'est jamais écrasé (le texte modifié par l'équipe est préservé).
 */
#[AsCommand(name: 'app:dossiers:importer-editoriaux', description: 'Copie les dossiers éditoriaux du code vers la base (modifiables dans l\'administration)')]
final class DossiersImportEditoriauxCommand extends Command
{
    public function __construct(private readonly Environment $twig, private readonly EntityManagerInterface $em, private readonly \Symfony\Component\HttpFoundation\RequestStack $stack)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('forcer', null, InputOption::VALUE_NONE, 'Réécrit le texte des dossiers déjà présents en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(Dossier::class);
        foreach (DossierController::DOSSIERS as $slug => [$title, $intro]) {
            $d = $repo->findOneBy(['slug' => $slug]);
            if ($d && !$input->getOption('forcer')) {
                $output->writeln("= $slug (déjà en base, conservé)");
                continue;
            }
            // Les gabarits lisent la requête courante (adresse canonique, langue) : on en simule une pour la ligne de commande.
            $req = \Symfony\Component\HttpFoundation\Request::create('https://lafranceendebat.fr/dossiers/' . $slug);
            $req->attributes->set('_route', 'app_dossier');
            $req->attributes->set('_route_params', ['slug' => $slug]);
            $req->setLocale('fr');
            $this->stack->push($req);
            try {
                $html = $this->twig->load('dossier/' . $slug . '.html.twig')->renderBlock('body', ['title' => $title, 'intro' => $intro]);
            } catch (\Throwable $e) {
                $output->writeln("<error>$slug : " . $e->getMessage() . '</error>');
                continue;
            } finally {
                $this->stack->pop();
            }
            [$md, $sources] = $this->convert($html);
            $d ??= (new Dossier())->setSlug($slug);
            $d->setTitle($title)->setIntro(mb_substr($intro, 0, 400))->setBody($md)->setSources(implode("\n", $sources ?: ['https://www.vie-publique.fr/']))->setEditorial(true);
            if (!$d->isPublished()) {
                $d->publish();
            }
            $this->em->persist($d);
            $this->em->flush();
            $output->writeln(sprintf('+ %s : %d caractères, %d sources', $slug, mb_strlen($md), count($sources)));
        }

        return Command::SUCCESS;
    }

    /** @return array{0:string,1:list<string>} [markdown, sources https] */
    private function convert(string $html): array
    {
        $root = (new Crawler('<div id="r">' . $html . '</div>'))->filter('#r');
        $sources = [];
        $out = [];
        foreach ($root->filter('section')->count() ? $root->filter('section')->first()->filter('.container')->children() : $root->children() as $node) {
            $this->block($node, $out, $sources);
        }

        return [trim(implode("\n\n", array_filter($out, static fn ($b) => '' !== trim($b)))), array_values(array_unique($sources))];
    }

    private function block(\DOMNode $n, array &$out, array &$sources): void
    {
        if (!$n instanceof \DOMElement) {
            return;
        }
        $tag = strtolower($n->tagName);
        switch ($tag) {
            case 'h1':
            case 'h2':
                $out[] = '## ' . $this->inline($n, $sources);
                break;
            case 'h3':
            case 'h4':
                $out[] = '### ' . $this->inline($n, $sources);
                break;
            case 'ul':
            case 'ol':
                $i = 0;
                $lines = [];
                foreach ($n->childNodes as $li) {
                    if ($li instanceof \DOMElement && 'li' === strtolower($li->tagName)) {
                        $lines[] = ('ol' === $tag ? (++$i) . '. ' : '- ') . $this->inline($li, $sources);
                    }
                }
                $out[] = implode("\n", $lines);
                break;
            case 'p':
            case 'blockquote':
                $t = $this->inline($n, $sources);
                $cls = $n->getAttribute('class');
                $out[] = ('blockquote' === $tag || str_contains($cls, 'carte') || str_contains($cls, 'limite')) ? '> ' . $t : $t;
                break;
            case 'a':
                break; // boutons d'action : sans objet dans un dossier édité
            default:
                if ($n->hasChildNodes()) {
                    foreach ($n->childNodes as $c) {
                        $this->block($c, $out, $sources);
                    }
                }
        }
    }

    private function inline(\DOMNode $n, array &$sources): string
    {
        $s = '';
        foreach ($n->childNodes as $c) {
            if ($c instanceof \DOMText) {
                $s .= $c->textContent;
            } elseif ($c instanceof \DOMElement) {
                $t = $this->inline($c, $sources);
                switch (strtolower($c->tagName)) {
                    case 'strong':
                    case 'b':
                        $s .= '' === trim($t) ? $t : '**' . trim($t) . '**';
                        break;
                    case 'em':
                    case 'i':
                        $s .= '' === trim($t) ? $t : '*' . trim($t) . '*';
                        break;
                    case 'br':
                        $s .= ' ';
                        break;
                    case 'a':
                        $h = trim($c->getAttribute('href'));
                        if (preg_match('#^https://\S+$#i', $h)) {
                            $sources[] = $h;
                            $s .= '[' . trim($t) . '](' . $h . ')';
                        } elseif (preg_match('#^/(?!/)\S*$#', $h)) {
                            $s .= '[' . trim($t) . '](' . $h . ')';
                        } else {
                            $s .= $t;
                        }
                        break;
                    default:
                        $s .= $t;
                }
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }
}
