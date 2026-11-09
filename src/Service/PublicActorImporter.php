<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Imports automatiques depuis les donnees ouvertes officielles. Seules les adresses professionnelles
 * PUBLIEES par l'organisme sont reprises, avec la page source. Les memes garde-fous que le CSV s'appliquent
 * (ActorImporter::addOne). Rien n'est envoye : les fiches restent a valider.
 */
class PublicActorImporter
{
    public const SOURCES = ['annuaire', 'assemblee', 'senat'];
    private const ANNUAIRE = 'https://api-lannuaire.service-public.gouv.fr/api/explore/v2.1/catalog/datasets/api-lannuaire-administration/records';
    private const AN_ZIP = 'https://data.assemblee-nationale.fr/static/openData/repository/17/amo/deputes_actifs_mandats_actifs_organes/AMO10_deputes_actifs_mandats_actifs_organes.json.zip';
    private const SENAT_CSV = 'https://data.senat.fr/data/senateurs/ODSEN_GENERAL.csv';

    /** Position a reprendre si la derniere execution de l'annuaire s'est arretee faute de temps (null = terminee). */
    public ?int $nextOffset = null;

    public function __construct(private readonly HttpClientInterface $http, private readonly ActorImporter $importer, private readonly EntityManagerInterface $em)
    {
    }

    /** @return array{read:int,added:int,skipped:int,refused:int,reasons:array<string,int>,error:?string} */
    public function run(string $source, bool $dry = false, array $opts = []): array
    {
        $stats = ['read' => 0, 'added' => 0, 'skipped' => 0, 'refused' => 0, 'reasons' => [], 'error' => null];
        $this->nextOffset = null;
        try {
            $rows = match ($source) {
                'annuaire' => $this->annuaire($opts),
                'assemblee' => $this->assemblee(),
                'senat' => $this->senat(),
                default => throw new \InvalidArgumentException('Source inconnue.'),
            };
            $n = 0;
            foreach ($rows as $row) {
                ++$stats['read'];
                $res = $dry ? $this->wouldAdd($row) : $this->importer->addOne($row);
                if ('added' === $res) {
                    ++$stats['added'];
                } elseif ('skipped' === $res) {
                    ++$stats['skipped'];
                } else {
                    ++$stats['refused'];
                    $stats['reasons'][$res] = ($stats['reasons'][$res] ?? 0) + 1;
                }
                if (!$dry && 0 === (++$n % 200)) {
                    $this->em->flush();
                }
            }
            if (!$dry) {
                $this->em->flush();
            }
        } catch (\Throwable $e) {
            $stats['error'] = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Source indisponible : ' . $e->getMessage();
        }

        return $stats;
    }

    /** En simulation, on applique les controles sans rien enregistrer. */
    private function wouldAdd(array $row): string
    {
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
        if ($email && $this->em->getRepository(\App\Entity\ActorContact::class)->findOneBy(['email' => $email])) {
            return 'skipped';
        }

        return $this->importer->check($email, $row) ?: 'added';
    }

    /** @return \Generator<array> */
    private function annuaire(array $opts): \Generator
    {
        $search = preg_replace('/[^\p{L}\p{N} \-\x27]/u', '', (string) ($opts['recherche'] ?? 'Mairie'));
        $where = 'adresse_courriel is not null and search(nom, "' . $search . '")';
        if ($dep = ($opts['departement'] ?? null)) {
            if (!preg_match('/^(\d{2}|2A|2B|97\d)$/', (string) $dep)) {
                throw new \InvalidArgumentException('Departement invalide.');
            }
            $where .= ' and startswith(code_insee_commune, "' . $dep . '")';
        }
        $max = max(1, min(9900, (int) ($opts['limite'] ?? 500)));
        $deadline = isset($opts['deadline']) ? (float) $opts['deadline'] : null;
        for ($offset = max(0, (int) ($opts['offset'] ?? 0)); $offset < $max; $offset += 100) {
            if (null !== $deadline && microtime(true) > $deadline) {
                $this->nextOffset = $offset; // temps epuise : on reprendra ici a la prochaine execution

                return;
            }
            $data = $this->http->request('GET', self::ANNUAIRE, ['query' => ['where' => $where, 'limit' => min(100, $max - $offset), 'offset' => $offset, 'order_by' => 'id'], 'timeout' => 20])->toArray();
            $rows = $data['results'] ?? [];
            foreach ($rows as $f) {
                yield $this->mapAnnuaire($f);
            }
            if (count($rows) < 100) {
                return;
            }
            usleep(400000);
        }
    }

    public function mapAnnuaire(array $f): array
    {
        $json = static fn ($v): array => is_string($v) && '' !== $v ? (json_decode($v, true) ?: []) : (is_array($v) ? $v : []);
        $addr = $json($f['adresse'] ?? null);
        $main = $addr[0] ?? [];
        foreach ($addr as $a) {
            if (($a['type_adresse'] ?? '') === 'Adresse') {
                $main = $a;
                break;
            }
        }
        $type = $json($f['pivot'] ?? null)[0]['type_service_local'] ?? '';
        $category = match (true) {
            str_contains($type, 'mairie') => 'commune',
            str_contains($type, 'departement') => 'departement',
            str_contains($type, 'region') => 'region',
            default => 'administration',
        };

        return [
            'email' => $f['adresse_courriel'] ?? '',
            'organisation' => (string) ($f['nom'] ?? ''),
            'source_url' => (string) ($f['url_service_public'] ?? ''),
            'categorie' => $category,
            'official' => true, // adresse de l'organisme publiee par l'Annuaire officiel : un FAI (orange.fr...) est admis pour une mairie
            'telephone' => $json($f['telephone'] ?? null)[0]['valeur'] ?? null,
            'site_web' => $json($f['site_internet'] ?? null)[0]['valeur'] ?? null,
            'adresse' => trim(($main['numero_voie'] ?? '') . ' ' . ($main['complement1'] ?? '')),
            'code_postal' => $main['code_postal'] ?? null,
            'ville' => $main['nom_commune'] ?? null,
        ];
    }

    /** Deputes en exercice : adresse "Mel" institutionnelle publiee dans les donnees ouvertes de l'Assemblee. */
    private function assemblee(): \Generator
    {
        $tmp = tempnam(sys_get_temp_dir(), 'an');
        try {
            file_put_contents($tmp, $this->http->request('GET', self::AN_ZIP, ['timeout' => 60])->getContent());
            $zip = new \ZipArchive();
            if (true !== $zip->open($tmp)) {
                throw new \RuntimeException('Archive illisible.');
            }
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = $zip->getNameIndex($i);
                if (!str_contains($name, 'acteur/') || !str_ends_with($name, '.json')) {
                    continue;
                }
                $row = $this->mapDepute(json_decode((string) $zip->getFromIndex($i), true)['acteur'] ?? []);
                if ($row) {
                    yield $row;
                }
            }
            $zip->close();
        } finally {
            @unlink($tmp);
        }
    }

    public function mapDepute(array $a): ?array
    {
        $list = $a['adresses']['adresse'] ?? [];
        $list = isset($list['uid']) ? [$list] : $list;
        $mail = null;
        $site = null;
        foreach ($list as $x) {
            if (($x['typeLibelle'] ?? '') === 'Mèl' && !$mail && str_ends_with(strtolower((string) ($x['valElec'] ?? '')), '@assemblee-nationale.fr')) {
                $mail = $x['valElec'];
            }
            if (($x['typeLibelle'] ?? '') === 'Site internet' && !$site) {
                $site = $x['valElec'];
            }
        }
        $uid = $a['uid']['#text'] ?? $a['uid'] ?? null;
        $id = $a['etatCivil']['ident'] ?? [];
        if (!$mail || !is_string($uid) || !isset($id['nom'])) {
            return null;
        }

        return [
            'email' => $mail,
            'organisation' => trim(($id['civ'] ?? '') . ' ' . ($id['prenom'] ?? '') . ' ' . $id['nom']) . ', député',
            'fonction' => 'Député',
            'source_url' => 'https://www2.assemblee-nationale.fr/deputes/fiche/OMC_' . $uid,
            'categorie' => 'parlementaire',
            'site_web' => is_string($site) && str_starts_with($site, 'http') ? $site : null,
        ];
    }

    private function senat(): \Generator
    {
        $csv = $this->http->request('GET', self::SENAT_CSV, ['timeout' => 60])->getContent();
        if (!mb_check_encoding($csv, 'UTF-8')) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'ISO-8859-1');
        }
        $lines = array_filter(preg_split('/\R/', $csv) ?: [], static fn ($l) => '' !== trim($l) && !str_starts_with($l, '%'));
        $head = null;
        foreach ($lines as $line) {
            $cols = str_getcsv($line, ',', '"', '');
            if (null === $head) {
                $head = array_flip($cols);
                continue;
            }
            $row = $this->mapSenateur($cols, $head);
            if ($row) {
                yield $row;
            }
        }
    }

    public function mapSenateur(array $c, array $h): ?array
    {
        $get = static fn (string $k): string => trim((string) ($c[$h[$k] ?? -1] ?? ''));
        $mail = $get('Courrier électronique');
        if ('ACTIF' !== $get('État') || !str_ends_with(strtolower($mail), '@senat.fr')) {
            return null;
        }

        return [
            'email' => $mail,
            'organisation' => trim($get('Qualité') . ' ' . $get('Prénom usuel') . ' ' . $get('Nom usuel')) . ', sénateur',
            'fonction' => 'Sénateur',
            'source_url' => 'https://data.senat.fr/les-senateurs/',
            'categorie' => 'parlementaire',
        ];
    }
}
