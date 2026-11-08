<?php

namespace App\Twig;

use App\Service\ContentTranslator;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** tr(entite, 'champ') : texte dans la langue du lecteur si une traduction est publiée. tr_note(entite) : 'auto', 'user' ou ''. */
class TranslationExtension extends AbstractExtension
{
    public function __construct(private readonly ContentTranslator $translator, private readonly RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tr', fn (object $e, string $field): string => $this->translator->text($e, $field, $this->locale($e))),
            new TwigFunction('tr_source', fn (object $e): string => $this->translator->sourceLocale($e) ?? 'fr'),
            new TwigFunction('tr_note', function (object $e): string {
                $t = $this->translator->translation($e, $this->locale($e));

                return $t ? $t->getSource() : '';
            }),
        ];
    }

    private function locale(object $e): string
    {
        return $this->requests->getCurrentRequest()?->getLocale() ?? 'fr';
    }
}
