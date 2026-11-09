<?php

namespace App\Twig;

use App\Service\Languages;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class LanguageExtension extends AbstractExtension
{
    public function __construct(private readonly Languages $languages, private readonly RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('languages', fn () => $this->languages->all()),
            new TwigFunction('lang_dir', fn (string $c) => $this->languages->isRtl($c) ? 'rtl' : 'ltr'),
            new TwigFunction('lang_name', fn (string $c) => $this->languages->name($c)),
            new TwigFunction('lang_has_ui', fn (string $c) => $this->languages->hasUi($c)),
            new TwigFunction('lang_prompt', fn () => (bool) $this->requests->getMainRequest()?->attributes->get('_lang_prompt', false)),
            new TwigFunction('current_path', fn () => $this->requests->getMainRequest()?->getRequestUri() ?? '/'),
        ];
    }
}
