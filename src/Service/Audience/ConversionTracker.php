<?php

namespace App\Service\Audience;

use Doctrine\DBAL\Connection;

/**
 * Compte les étapes qui comptent pour le projet : inscription, première contribution, contribution, candidature.
 *
 * Pour ajouter une étape : l'ajouter à EVENTS, puis appeler record() à l'endroit où elle se produit
 * (ou compléter ConversionListener si elle correspond à la création d'une entité).
 * Garde-fous : le nom doit figurer dans EVENTS (pas de texte libre), un échec d'écriture ne doit jamais casser la page.
 */
class ConversionTracker
{
    public const EVENTS = [
        'inscription' => 'Inscriptions',
        'contribution' => 'Contributions (propositions et dépôts anonymes)',
        'premiere_contribution' => 'Premières contributions d\'un membre',
        'candidature_mission' => 'Candidatures à une mission',
        'candidature_contact' => 'Demandes de contact',
        'soutien_initiative' => 'Soutiens à l\'initiative',
    ];

    public function __construct(private readonly Connection $db)
    {
    }

    public function record(string $event, ?\DateTimeImmutable $at = null): void
    {
        if (!isset(self::EVENTS[$event])) {
            return;
        }
        $day = ($at ?? new \DateTimeImmutable())->format('Y-m-d');
        try {
            $n = $this->db->executeStatement('UPDATE conversion_count SET total = total + 1 WHERE day = ? AND name = ?', [$day, $event]);
            if (0 === $n) {
                $this->db->executeStatement('INSERT INTO conversion_count (day, name, total) VALUES (?, ?, 1)', [$day, $event]);
            }
        } catch (\Throwable) {
            // La mesure ne doit jamais empêcher une inscription ou une contribution.
        }
    }

    /**
     * @return list<array{day:string,visits:int,events:array<string,int>}> du plus récent au plus ancien
     */
    public function lastDays(int $days = 30): array
    {
        $since = (new \DateTimeImmutable('-' . ($days - 1) . ' days'))->format('Y-m-d');
        $events = [];
        foreach ($this->db->fetchAllAssociative('SELECT day, name, total FROM conversion_count WHERE day >= ?', [$since]) as $r) {
            $events[$r['day']][$r['name']] = (int) $r['total'];
        }
        $visits = [];
        foreach ($this->db->fetchAllAssociative('SELECT visited_at FROM site_visitor WHERE visited_at >= ?', [$since . ' 00:00:00']) as $r) {
            $d = substr((string) $r['visited_at'], 0, 10);
            $visits[$d] = ($visits[$d] ?? 0) + 1;
        }
        $out = [];
        for ($i = 0; $i < $days; ++$i) {
            $d = (new \DateTimeImmutable('-' . $i . ' days'))->format('Y-m-d');
            $out[] = ['day' => $d, 'visits' => $visits[$d] ?? 0, 'events' => $events[$d] ?? []];
        }

        return $out;
    }
}
