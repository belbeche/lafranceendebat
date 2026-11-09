<?php

namespace App\Service\Positions;

use App\Entity\GroupVote;
use App\Entity\PositionStatement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Relie une affirmation de la page Positions à des scrutins publics de l'Assemblée nationale.
 *
 * Les chiffres viennent des données ouvertes officielles (scrutins de la législature 17, noms des groupes
 * tirés du référentiel des organes). Rien n'est interprété : on stocke pour, contre, abstention par groupe,
 * et l'affirmation garde un lien vers la fiche officielle du scrutin. Les deux archives sont mises en cache
 * 24 h dans var/cache/an pour ne pas les retélécharger à chaque appel.
 *
 * Pour passer à une autre législature, changer LEGISLATURE et les deux adresses ci-dessous.
 */
class ScrutinImporter
{
    public const LEGISLATURE = 17;
    public const SCRUTINS_ZIP = 'https://data.assemblee-nationale.fr/static/openData/repository/17/loi/scrutins/Scrutins.json.zip';
    public const ORGANES_ZIP = 'https://data.assemblee-nationale.fr/static/openData/repository/17/amo/deputes_actifs_mandats_actifs_organes/AMO10_deputes_actifs_mandats_actifs_organes.json.zip';
    public const SCRUTIN_URL = 'https://www.assemblee-nationale.fr/dyn/17/scrutins/%d';

    /** @var array<string,array{code:string,name:string}> */
    private array $organes = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $http,
        #[Autowire('%kernel.cache_dir%')] private readonly string $cacheDir,
    ) {
    }

    /**
     * Cherche des scrutins par mots du titre (tous les mots doivent y figurer).
     *
     * @return list<array{numero:int,date:string,titre:string,sort:string,pour:int,contre:int}>
     */
    public function search(string $keywords, int $limit = 15): array
    {
        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($keywords))) ?: []));
        if (!$words) {
            return [];
        }
        $zip = $this->open(self::SCRUTINS_ZIP, 'scrutins.zip');
        $out = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $raw = $zip->getFromIndex($i);
            if (false === $raw) {
                continue;
            }
            $lower = mb_strtolower($raw);
            foreach ($words as $w) {
                if (!str_contains($lower, $w)) {
                    continue 2;
                }
            }
            $d = json_decode($raw, true)['scrutin'] ?? null;
            if (!$d) {
                continue;
            }
            $title = mb_strtolower((string) ($d['titre'] ?? ''));
            foreach ($words as $w) {
                if (!str_contains($title, $w)) {
                    continue 2;
                }
            }
            $out[] = ['numero' => (int) $d['numero'], 'date' => (string) $d['dateScrutin'], 'titre' => (string) $d['titre'], 'sort' => (string) ($d['sort']['code'] ?? ''), 'pour' => (int) ($d['syntheseVote']['decompte']['pour'] ?? 0), 'contre' => (int) ($d['syntheseVote']['decompte']['contre'] ?? 0)];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return array_slice($out, 0, $limit);
    }

    /**
     * Enregistre les votes de chaque groupe pour un scrutin. Rejouable : un scrutin déjà lié est mis à jour.
     *
     * @return int nombre de groupes enregistrés
     */
    public function import(PositionStatement $s, int $numero): int
    {
        $zip = $this->open(self::SCRUTINS_ZIP, 'scrutins.zip');
        $raw = $zip->getFromName('json/VTANR5L' . self::LEGISLATURE . 'V' . $numero . '.json');
        if (false === $raw) {
            throw new \RuntimeException(sprintf('Scrutin %d introuvable dans les données officielles.', $numero));
        }

        return $this->saveFromData($s, json_decode($raw, true)['scrutin'] ?? []);
    }

    /**
     * Cœur de l'import, séparé du téléchargement pour être testé avec un jeu de données fourni.
     *
     * @param array<string,mixed> $d contenu de la clé "scrutin" d'un fichier officiel
     * @param array<string,array{code:string,name:string}>|null $organes remplace le référentiel téléchargé (tests)
     */
    public function saveFromData(PositionStatement $s, array $d, ?array $organes = null): int
    {
        $numero = (int) ($d['numero'] ?? 0);
        $groups = $d['ventilationVotes']['organe']['groupes']['groupe'] ?? [];
        if ($numero < 1 || !$groups) {
            throw new \RuntimeException('Données de scrutin incomplètes.');
        }
        if (isset($groups['organeRef'])) {
            $groups = [$groups];
        }
        $date = !empty($d['dateScrutin']) ? new \DateTimeImmutable((string) $d['dateScrutin']) : null;
        $url = sprintf(self::SCRUTIN_URL, $numero);
        $n = 0;
        foreach ($groups as $g) {
            $ref = (string) ($g['organeRef'] ?? '');
            $c = $g['vote']['decompteVoix'] ?? [];
            $info = $organes[$ref] ?? $this->organe($ref);
            $existing = $this->em->getRepository(GroupVote::class)->findOneBy(['statement' => $s, 'scrutin' => $numero, 'groupCode' => $info['code']]);
            if ($existing) {
                $this->em->remove($existing);
                $this->em->flush();
            }
            $this->em->persist(new GroupVote($s, $numero, $url, $info['code'], $info['name'], (int) ($c['pour'] ?? 0), (int) ($c['contre'] ?? 0), (int) ($c['abstentions'] ?? 0), $date));
            ++$n;
        }
        $this->em->flush();

        return $n;
    }

    /** @return array{code:string,name:string} */
    private function organe(string $ref): array
    {
        if (!$this->organes) {
            $zip = $this->open(self::ORGANES_ZIP, 'organes.zip');
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = (string) $zip->getNameIndex($i);
                if (!str_starts_with($name, 'json/organe/')) {
                    continue;
                }
                $o = json_decode((string) $zip->getFromIndex($i), true)['organe'] ?? [];
                if ('GP' === ($o['codeType'] ?? '')) {
                    $this->organes[(string) $o['uid']] = ['code' => (string) ($o['libelleAbrege'] ?? $o['uid']), 'name' => (string) ($o['libelle'] ?? $o['uid'])];
                }
            }
        }

        return $this->organes[$ref] ?? ['code' => $ref, 'name' => 'Groupe ' . $ref . ' (ancien groupe)'];
    }

    private function open(string $url, string $file): \ZipArchive
    {
        $dir = $this->cacheDir . '/an';
        @mkdir($dir, 0775, true);
        $path = $dir . '/' . $file;
        if (!is_file($path) || filemtime($path) < time() - 86400) {
            $fh = fopen($path . '.part', 'wb');
            foreach ($this->http->stream($this->http->request('GET', $url, ['timeout' => 120, 'max_duration' => 600])) as $chunk) {
                fwrite($fh, $chunk->getContent());
            }
            fclose($fh);
            rename($path . '.part', $path);
        }
        $zip = new \ZipArchive();
        if (true !== $zip->open($path)) {
            @unlink($path);
            throw new \RuntimeException('Archive officielle illisible : ' . $file);
        }

        return $zip;
    }
}
