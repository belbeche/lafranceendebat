<?php

namespace App\Service;

/**
 * Garde-fou : la place de marche ne doit servir ni a financer un parti ou une campagne, ni a leur fournir des services
 * (un service gratuit ou sous le prix du marche fourni a un candidat peut etre requalifie en don en nature).
 * Detection volontairement simple : elle bloque l'evident et renvoie vers la moderation, elle ne remplace pas la relecture humaine.
 */
class PoliticalGuard
{
    private const PATTERNS = [
        '#campagne\s+(électorale|de\s+(com|communication)|municipale|présidentielle|législative|régionale|départementale|européenne|sénatoriale)#iu',
        '#compte\s+de\s+campagne|mandataire\s+financier|financement\s+(de\s+)?(parti|campagne)|don[s]?\s+(à|au|aux)\s+(un\s+|une\s+|des\s+)?(candidat|parti)#iu',
        '#tractage|tract\s+électoral|propagande|démarchage\s+électoral|porte[-\s]à[-\s]porte\s+(électoral|militant)|affichage\s+électoral#iu',
        '#(soutenir|aider|promouvoir|faire\s+élire)\s+(la\s+candidature|un\s+candidat|une\s+candidate|son\s+candidat|le\s+parti)#iu',
        '#(pour|de)\s+(le|la|mon|notre)\s+(candidat|candidate|parti|liste\s+électorale)\b#iu',
        '#meeting\s+(politique|électoral)|militants?\s+(de|du)\s+parti#iu',
    ];

    /** Renvoie l'expression detectee, ou null si rien de suspect. */
    public function find(string $text): ?string
    {
        foreach (self::PATTERNS as $p) {
            if (preg_match($p, $text, $m)) {
                return trim($m[0]);
            }
        }

        return null;
    }
}
