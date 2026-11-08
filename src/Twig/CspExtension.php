<?php

namespace App\Twig;

use App\EventSubscriber\SecurityHeadersSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** csp_nonce() : jeton unique par requete autorisant uniquement les scripts en ligne que nous ecrivons. */
class CspExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('csp_nonce', fn (): string => SecurityHeadersSubscriber::nonce($this->requests->getMainRequest()))];
    }
}
