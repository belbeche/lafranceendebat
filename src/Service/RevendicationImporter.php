<?php

namespace App\Service;

use App\Entity\Proposition;
use App\Entity\Revendication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Import CSV de revendications. Mêmes règles pour tous les acteurs, institutions et partis : source https obligatoire,
 * faits sans insulte, jamais publié automatiquement (relecture puis publication par un administrateur).
 * En-têtes : titre;resume;groupe;theme;source_url;source_nom;autorite;langue;verification
 */
class RevendicationImporter
{
    public const MAX_ROWS = 500;
    public const MAX_BYTES = 1_000_000;

    public function __construct(private readonly EntityManagerInterface $em, private readonly SluggerInterface $slugger, private readonly ContentModerator $moderator)
    {
    }

    /** @return array{added:int, skipped:int, rejected:list<array{line:int,reason:string}>} */
    public function import(string $csv): array
    {
        $out = ['added' => 0, 'skipped' => 0, 'rejected' => []];
        if (strlen($csv) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Fichier trop volumineux (1 Mo maximum).');
        }
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $firstLine = strtok($csv, "\n") ?: '';
        $sep = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $rows = array_map(fn ($l) => str_getcsv($l, $sep, '"', ''), preg_split('/\r\n|\n|\r/', trim($csv)));
        $head = array_map(fn ($h) => self::col($h), array_shift($rows) ?: []);
        foreach (['titre', 'resume', 'source_url'] as $need) {
            if (!in_array($need, $head, true)) {
                throw new \InvalidArgumentException("Colonne obligatoire absente : $need. En-têtes attendus : titre;resume;groupe;theme;source_url;source_nom;autorite;langue;verification.");
            }
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new \InvalidArgumentException('Trop de lignes (' . self::MAX_ROWS . ' maximum par import).');
        }
        $repo = $this->em->getRepository(Revendication::class);
        foreach ($rows as $i => $row) {
            $line = $i + 2;
            if (1 === count($row) && '' === trim((string) $row[0])) {
                continue;
            }
            $r = [];
            foreach ($head as $c => $name) {
                $r[$name] = trim((string) ($row[$c] ?? ''));
            }
            $title = $r['titre'];
            if (mb_strlen($title) < 10 || mb_strlen($title) > 190) {
                $out['rejected'][] = ['line' => $line, 'reason' => 'Titre de 10 à 190 caractères requis.'];
                continue;
            }
            if (mb_strlen($r['resume']) < 60 || mb_strlen($r['resume']) > 3000) {
                $out['rejected'][] = ['line' => $line, 'reason' => 'Résumé de 60 à 3000 caractères requis (faits, qui, quand, conséquences).'];
                continue;
            }
            if (!preg_match('#^https://[^\s]+\.[^\s]+$#i', $r['source_url'])) {
                $out['rejected'][] = ['line' => $line, 'reason' => 'source_url manquante ou invalide (https obligatoire).'];
                continue;
            }
            if (array_intersect($this->moderator->analyze($title . "\n" . $r['resume']), [ContentModerator::REASON_INSULT, ContentModerator::REASON_THREAT, ContentModerator::REASON_PERSONAL_DATA])) {
                $out['rejected'][] = ['line' => $line, 'reason' => 'Insulte, menace ou donnée personnelle détectée : reformuler en faits.'];
                continue;
            }
            $base = strtolower((string) $this->slugger->slug($title));
            $slug = mb_substr($base, 0, 190) ?: 'revendication';
            if ($repo->findOneBy(['slug' => $slug])) {
                ++$out['skipped'];
                continue;
            }
            $group = in_array($r['groupe'] ?? '', Revendication::GROUPS, true) ? $r['groupe'] : 'Tous les citoyens';
            $theme = in_array($r['theme'] ?? '', Proposition::THEMES, true) ? $r['theme'] : Proposition::THEMES[0];
            $lang = strtolower($r['langue'] ?? '');
            $rev = (new Revendication())->setTitle($title)->setSlug($slug)->setSummary($r['resume'])->setGroupLabel($group)->setTheme($theme)
                ->setSourceUrl($r['source_url'])->setSourceLabel(($r['source_nom'] ?? '') ?: null)->setCompetentAuthority(($r['autorite'] ?? '') ?: null)
                ->setVerificationNote(($r['verification'] ?? '') ?: null)->setLocale(in_array($lang, Revendication::LOCALES, true) ? $lang : 'fr')
                ->setIsPublished(false)->setRaisedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
            $this->em->persist($rev);
            ++$out['added'];
        }
        $this->em->flush();

        return $out;
    }

    private static function col(string $c): string
    {
        $c = mb_strtolower(trim($c));
        $c = strtr($c, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', ' ' => '_', '-' => '_']);

        return match ($c) {
            'title', 'intitule' => 'titre',
            'summary', 'faits', 'description' => 'resume',
            'group', 'portee' => 'groupe',
            'source', 'url', 'lien' => 'source_url',
            'source_label', 'nom_source' => 'source_nom',
            'authority', 'autorite_competente' => 'autorite',
            'language', 'lang' => 'langue',
            'note', 'verification_note' => 'verification',
            default => $c,
        };
    }
}
