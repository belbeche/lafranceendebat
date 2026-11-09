<?php

namespace App\Twig;

use App\Service\ReliabilityChecker;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** reliability(texte, sources) : score indicatif affiché sous forme de pastille. */
class ReliabilityExtension extends AbstractExtension
{
    public function __construct(private ReliabilityChecker $checker)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('reliability', function (string $text, string|array $sources = []): array {
            $list = is_array($sources) ? $sources : array_values(array_filter(array_map('trim', preg_split('~\s+~', $sources) ?: [])));

            return $this->checker->assess($text, $list);
        })];
    }
}
