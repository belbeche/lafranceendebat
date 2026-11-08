<?php

namespace App\Service\Agenda;

use App\Service\ContentModerator;

/** Controle automatique d'un evenement importe : rien n'est publie sans avoir passe toutes les verifications. */
class EventVerifier
{
    /** Hotes de sources reconnues : le domaine ou l'un de ses sous-domaines. */
    public const ALLOWED_HOSTS = ['gouv.fr', 'service-public.fr', 'vie-publique.fr', 'assemblee-nationale.fr', 'senat.fr', 'elysee.fr', 'conseil-constitutionnel.fr', 'europa.eu', 'un.org', 'unesco.org'];

    public function __construct(private readonly ContentModerator $moderator)
    {
    }

    public static function hostAllowed(string $url): bool
    {
        if (!preg_match('#^https://[^\s/]+#i', $url)) {
            return false;
        }
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::ALLOWED_HOSTS as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> motifs de refus ; vide si l'evenement est conforme */
    public function problems(string $name, \DateTimeInterface $date, ?string $link, ?string $description): array
    {
        $p = [];
        $len = mb_strlen(trim($name));
        if ($len < 5 || $len > 200) {
            $p[] = 'titre de 5 à 200 caractères exigé';
        }
        $today = new \DateTimeImmutable('today');
        if ($date < $today->modify('-1 day') || $date > $today->modify('+18 months')) {
            $p[] = 'date hors de la fenêtre (hier à +18 mois)';
        }
        if (null !== $link && '' !== $link && !self::hostAllowed($link)) {
            $p[] = 'lien hors des sources officielles reconnues';
        }
        if ([] !== $this->moderator->analyze($name . ' ' . (string) $description)) {
            $p[] = 'terme non autorisé par la charte';
        }

        return $p;
    }
}
