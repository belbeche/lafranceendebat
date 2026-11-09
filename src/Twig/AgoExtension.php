<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/** Filtre "il y a 5 min" en francais pour les fils et les reponses. */
class AgoExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('ago', $this->ago(...))];
    }

    public function ago(\DateTimeInterface $date): string
    {
        $s = max(0, time() - $date->getTimestamp());

        return match (true) {
            $s < 60 => 'à l\'instant',
            $s < 3600 => 'il y a ' . intdiv($s, 60) . ' min',
            $s < 86400 => 'il y a ' . intdiv($s, 3600) . ' h',
            $s < 86400 * 7 => 'il y a ' . intdiv($s, 86400) . ' j',
            default => 'le ' . $date->format('d/m/Y'),
        };
    }
}
