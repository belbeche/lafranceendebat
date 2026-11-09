<?php

namespace App\Service\Programme;

use App\Entity\PartyProposal;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Propositions de loi deposees au Senat, par groupe (donnees ouvertes de data.senat.fr, licence ouverte :
 * source et date de mise a jour a citer).
 *
 *  - ppl.csv (Dosleg) : session, numero, date de depot, auteurs, titre, URL du dossier.
 *  - ODSEN_GENERAL.csv : groupe politique de chaque senateur.
 *
 * Regle de rattachement (identique a l'Assemblee, avec une limite annoncee) : le groupe d'une proposition est celui de son
 * PREMIER signataire. Les donnees ouvertes ne donnent que le groupe ACTUEL (ou dernier connu) du senateur : on n'importe donc
 * que les textes dont le premier signataire est un senateur EN EXERCICE ; les autres sont ignores plutot que mal attribues.
 * Les groupes du Senat portent le prefixe "SEN-" pour ne pas se confondre avec ceux de l'Assemblee.
 */
class SenatImporter
{
    /** Libelle du CSV du Senat => code court. Un libelle inconnu garde son propre texte, tronque. */
    public const SHORT = ['Les Républicains' => 'LR', 'Les Indépendants' => 'LIRT'];
    /** Noms complets, uniquement ceux dont on est certain ; sinon le sigle officiel seul est affiche. */
    public const GROUP_NAMES = [
        'LR' => 'Les Républicains', 'SER' => 'Socialiste, Écologiste et Républicain', 'RDSE' => 'Rassemblement Démocratique et Social Européen',
        'RDPI' => 'Rassemblement des démocrates, progressistes et indépendants', 'CRCE-K' => 'Communiste Républicain Citoyen et Écologiste - Kanaky',
        'GEST' => 'Écologiste - Solidarité et Territoires', 'LIRT' => 'Les Indépendants - République et Territoires',
        'NI' => 'Sénateurs ne figurant sur la liste d\'aucun groupe',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array{created:int,updated:int,skipped:int,noGroup:int} */
    public function import(string $pplCsv, string $senatorsCsv, int $sinceSession = 2023, ?int $limit = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'noGroup' => 0];
        $groups = self::groupsByName($senatorsCsv);
        $repo = $this->em->getRepository(PartyProposal::class);
        $n = 0;
        foreach (self::rows($pplCsv) as $r) {
            $session = (int) ($r['Session'] ?? 0);
            if ($session < $sinceSession || 'proposition de loi' !== mb_strtolower(trim($r['Type de dossier'] ?? ''))) {
                ++$stats['skipped'];
                continue;
            }
            $first = trim(explode(',', (string) ($r['Auteurs'] ?? ''))[0]);
            $code = $groups[self::key($first)] ?? null;
            $code = null === $code ? null : (self::SHORT[$code] ?? mb_substr($code, 0, 8));
            if (null === $code) {
                ++$stats['noGroup'];
                continue;
            }
            $url = preg_replace('~^http://(www\.senat\.fr/)~i', 'https://$1', trim((string) ($r['URL du dossier'] ?? '')));
            $title = trim((string) ($r['Titre'] ?? ''));
            if ('' === $title || !preg_match('~^https://~i', (string) $url)) {
                ++$stats['skipped'];
                continue;
            }
            $ext = sprintf('SEN-%d-%s', $session, trim((string) ($r['Numéro de texte'] ?? '')));
            $p = $repo->findOneBy(['externalId' => $ext]);
            $new = null === $p;
            $p ??= (new PartyProposal())->setExternalId($ext)->setSource(PartyProposal::SOURCE_SENAT);
            $p->setGroup('SEN-' . $code, (self::GROUP_NAMES[$code] ?? $code) . ' (Sénat)')
                ->setTitle(ucfirst($title))->setSourceUrl($url)->setDepositedAt(self::date($r['Date de dépôt'] ?? ''));
            if ($new) {
                $p->setTheme(ThemeGuesser::guess($title));
                $this->em->persist($p);
                ++$stats['created'];
            } else {
                ++$stats['updated'];
            }
            if (0 === (++$n % 100)) {
                $this->em->flush();
            }
            if ($limit && $n >= $limit) {
                break;
            }
        }
        $this->em->flush();

        return $stats;
    }

    /** @return array<string,string> "prenom nom" normalise => code du groupe, senateurs EN EXERCICE seulement */
    public static function groupsByName(string $csv): array
    {
        $out = [];
        $csv = self::utf8($csv);
        $lines = array_values(array_filter(preg_split('/\R/', $csv) ?: [], static fn ($l) => '' !== trim($l) && !str_starts_with($l, '%')));
        $head = null;
        foreach ($lines as $line) {
            $c = str_getcsv($line, ',', '"', '');
            if (null === $head) {
                $head = array_flip($c);
                continue;
            }
            $get = static fn (string $k): string => trim((string) ($c[$head[$k] ?? -1] ?? ''));
            if ('ACTIF' !== $get('État') || '' === $get('Groupe politique')) {
                continue;
            }
            $out[self::key($get('Prénom usuel') . ' ' . $get('Nom usuel'))] = $get('Groupe politique');
        }

        return $out;
    }

    /** @return \Generator<array<string,string>> */
    public static function rows(string $csv): \Generator
    {
        $csv = self::utf8($csv);
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $csv);
        rewind($fh);
        $head = fgetcsv($fh, 0, ';', '"', '');
        while (false !== ($c = fgetcsv($fh, 0, ';', '"', ''))) {
            if ($head && count($c) === count($head)) {
                yield array_combine($head, $c);
            }
        }
        fclose($fh);
    }

    public static function key(string $name): string
    {
        $n = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        $n = strtr($n, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'"]);

        return $n;
    }

    private static function utf8(string $csv): string
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);

        return mb_check_encoding($csv, 'UTF-8') ? $csv : mb_convert_encoding($csv, 'UTF-8', 'ISO-8859-1');
    }

    private static function date(string $d): ?\DateTimeImmutable
    {
        $x = \DateTimeImmutable::createFromFormat('!d/m/Y', trim($d));

        return $x ?: null;
    }
}
