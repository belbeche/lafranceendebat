<?php

namespace App\Twig;

use App\Service\DossierCatalog;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig « DossierExtension ».
 */
class DossierExtension extends AbstractExtension
{
    public function __construct(private readonly DossierCatalog $catalog, private readonly UrlGeneratorInterface $urls) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('dossier_link', $this->link(...)), new TwigFunction('dossier_theme_slug', fn (?string $t) => $t && isset(DossierCatalog::STARTERS[$t]) ? DossierCatalog::starterSlug($t) : null)];
    }

    /** @return array{title:string,url:string}|null */
    public function link(?string $slug): ?array
    {
        $l = $this->catalog->link($slug);
        if (!$l) {
            return null;
        }

        return ['title' => $l['title'], 'url' => $this->urls->generate($l['builtin'] ? 'app_dossier' : 'app_dossier_page', ['slug' => $l['slug']])];
    }
}
