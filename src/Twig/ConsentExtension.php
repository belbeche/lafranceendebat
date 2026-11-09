<?php

namespace App\Twig;

use App\Service\Audience\AudienceOptOut;
use App\Service\Audience\ConsentManager;
use App\Service\Audience\ViewTracker;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ConsentExtension extends AbstractExtension
{
    public function __construct(private readonly ConsentManager $consent, private readonly RequestStack $requests, private readonly AudienceOptOut $optOut)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('consent_current', fn (): ?array => ($r = $this->requests->getMainRequest()) ? $this->consent->current($r) : null),
            new TwigFunction('optout_state', fn (): array => ($r = $this->requests->getMainRequest()) ? ['cookie' => $this->optOut->hasCookie($r), 'signal' => $this->optOut->hasBrowserSignal($r)] : ['cookie' => false, 'signal' => false]),
            new TwigFunction('consent_audience', fn (): bool => ($r = $this->requests->getMainRequest()) ? $this->consent->hasAudience($r) : false),
        ];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('duration', static fn ($s): string => ViewTracker::duration((int) $s))];
    }
}
