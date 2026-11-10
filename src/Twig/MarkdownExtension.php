<?php

namespace App\Twig;

use App\Service\Content\SafeMarkdown;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/** Filtres {{ texte|md }} (texte mis en forme, sûr) et {{ texte|md_plain(200) }} (extrait sans marques). */
final class MarkdownExtension extends AbstractExtension
{
    public function __construct(private readonly SafeMarkdown $md)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('md', fn (?string $t) => $this->md->render((string) $t), ['is_safe' => ['html']]),
            new TwigFilter('md_plain', fn (?string $t, int $max = 200) => $this->md->plain((string) $t, $max)),
        ];
    }
}
