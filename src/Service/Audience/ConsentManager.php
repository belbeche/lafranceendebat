<?php

namespace App\Service\Audience;

use App\Entity\ConsentRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gestion maison du consentement. Un seul cookie propre au site (lfed_consent), valable 6 mois,
 * contient : version du texte, identifiant aleatoire du navigateur, choix "mesure de lecture".
 * Les cookies strictement necessaires (session, jeton de formulaire) ne passent pas par ici.
 * Aucun traceur tiers n'existe sur le site : rien n'est charge chez un autre service.
 */
class ConsentManager
{
    public const VERSION = 1;
    public const COOKIE = 'lfed_consent';
    public const MONTHS = 6;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array{version: int, visitorId: string, audience: bool}|null null = aucun choix valable (jamais choisi, ou texte modifie depuis) */
    public function current(Request $r): ?array
    {
        $raw = (string) $r->cookies->get(self::COOKIE, '');
        if (!preg_match('/^(\d{1,3})\.([a-f0-9]{32})\.a([01])$/', $raw, $m) || (int) $m[1] !== self::VERSION) {
            return null;
        }

        return ['version' => (int) $m[1], 'visitorId' => $m[2], 'audience' => '1' === $m[3]];
    }

    public function hasAudience(Request $r): bool
    {
        return (bool) ($this->current($r)['audience'] ?? false);
    }

    /** Enregistre le choix : preuve en base + cookie. $action : accept_all | refuse_all | custom | withdraw. */
    public function record(Request $r, Response $resp, bool $audience, string $action): void
    {
        $prev = (string) $r->cookies->get(self::COOKIE, '');
        $visitor = preg_match('/^\d{1,3}\.([a-f0-9]{32})\./', $prev, $m) ? $m[1] : bin2hex(random_bytes(16));
        $this->em->persist(new ConsentRecord($visitor, self::VERSION, $audience, $action));
        $this->em->flush();
        $resp->headers->setCookie(Cookie::create(self::COOKIE, sprintf('%d.%s.a%d', self::VERSION, $visitor, $audience ? 1 : 0))
            ->withExpires(new \DateTimeImmutable('+' . self::MONTHS . ' months'))->withPath('/')
            ->withSecure($r->isSecure())->withHttpOnly(false)->withSameSite('lax'));
    }
}
