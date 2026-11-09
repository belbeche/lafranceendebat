<?php

namespace App\Service\Audience;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Droit d'opposition à la mesure d'audience interne (RGPD art. 21, exigé même pour un outil exempté de consentement).
 *
 * Une personne n'est PAS mesurée si l'une de ces trois conditions est vraie :
 *  1. elle a cliqué sur "Désactiver le suivi" (cookie technique `lfed_notrack=1`, durée 13 mois, ne contient aucune donnée personnelle) ;
 *  2. son navigateur envoie le signal Global Privacy Control (`Sec-GPC: 1`) ;
 *  3. son navigateur envoie "Do Not Track" (`DNT: 1`).
 *
 * Quand une personne s'oppose, la requête n'est comptée nulle part : ni visite, ni lecture, ni temps de lecture.
 */
final class AudienceOptOut
{
    public const COOKIE = 'lfed_notrack';

    public function isOptedOut(Request $r): bool
    {
        return '1' === $r->cookies->get(self::COOKIE) || $this->hasBrowserSignal($r);
    }

    /** Signal envoyé par le navigateur lui-même (GPC ou DNT), indépendant de notre cookie. */
    public function hasBrowserSignal(Request $r): bool
    {
        return '1' === $r->headers->get('Sec-GPC') || '1' === $r->headers->get('DNT');
    }

    public function hasCookie(Request $r): bool
    {
        return '1' === $r->cookies->get(self::COOKIE);
    }

    public function optOut(Request $r, Response $resp): void
    {
        $resp->headers->setCookie(Cookie::create(self::COOKIE, '1', new \DateTimeImmutable('+13 months'), '/', null, $r->isSecure(), true, false, Cookie::SAMESITE_LAX));
    }

    public function optIn(Request $r, Response $resp): void
    {
        $resp->headers->clearCookie(self::COOKIE, '/', null, $r->isSecure(), true, Cookie::SAMESITE_LAX);
    }
}
