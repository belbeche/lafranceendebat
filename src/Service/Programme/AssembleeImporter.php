<?php

namespace App\Service\Programme;

use App\Entity\PartyProposal;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Importe les propositions de loi déposées à l'Assemblée nationale depuis les données ouvertes officielles
 * (licence ouverte Etalab : la source et la date de mise à jour doivent être mentionnées, ce que fait l'interface).
 *
 * Deux jeux de données, au format JSON, déjà décompressés dans deux dossiers :
 *  - dossiers "document/" : un fichier par texte (PIONANR5L17B0016.json…) ;
 *  - dossiers "acteur/" et "organe/" : mandats des députés et groupes politiques.
 *
 * Règle de rattachement, publique et vérifiable : le groupe d'une proposition est celui de son PREMIER signataire
 * à la date de dépôt. Si l'auteur est lui-même un organe (groupe), il est retenu tel quel. Sans groupe identifiable,
 * la proposition est ignorée (jamais devinée). Relancer l'import met à jour sans créer de doublon.
 */
class AssembleeImporter
{
    /** Page du texte (vérifiée) pour une proposition de loi ordinaire ; les autres sous-types (organique, constitutionnelle…) ont un autre nom de page, on renvoie alors à la version ouverte du document. */
    public const TEXT_URL = 'https://www.assemblee-nationale.fr/dyn/%d/textes/%s_proposition-loi';
    public const OPENDATA_URL = 'https://www.assemblee-nationale.fr/dyn/opendata/%s.html';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @return array{created:int,updated:int,skipped:int,noGroup:int}
     */
    public function import(string $documentDir, string $actorDir, string $organeDir, int $legislature, ?int $limit = null): array
    {
        $groups = $this->loadGroups($organeDir, $legislature);
        $mandates = $this->loadMandates($actorDir, $legislature, $groups);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'noGroup' => 0];
        $repo = $this->em->getRepository(PartyProposal::class);
        $files = glob(rtrim($documentDir, '/') . sprintf('/PIONANR5L%dB*.json', $legislature)) ?: [];
        sort($files);
        $n = 0;
        foreach ($files as $file) {
            $doc = json_decode((string) file_get_contents($file), true)['document'] ?? null;
            if (!is_array($doc) || 'PION' !== ($doc['classification']['type']['code'] ?? null)) {
                ++$stats['skipped'];
                continue;
            }
            $deposit = $this->date($doc['cycleDeVie']['chrono']['dateDepot'] ?? null);
            $group = $this->resolveGroup($doc['auteurs']['auteur'] ?? null, $deposit, $mandates, $groups);
            if (null === $group) {
                ++$stats['noGroup'];
                continue;
            }
            $uid = (string) $doc['uid'];
            $title = $this->clean((string) ($doc['titres']['titrePrincipalCourt'] ?? $doc['titres']['titrePrincipal'] ?? ''));
            $full = $this->clean((string) ($doc['titres']['titrePrincipal'] ?? ''));
            if ('' === $title) {
                ++$stats['skipped'];
                continue;
            }
            $p = $repo->findOneBy(['externalId' => $uid]);
            $isNew = null === $p;
            $p ??= (new PartyProposal())->setExternalId($uid)->setSource(PartyProposal::SOURCE_AN);
            $p->setTitle(mb_strtoupper(mb_substr($title, 0, 1)) . mb_substr($title, 1))
                ->setSummary($full !== $title ? mb_strtoupper(mb_substr($full, 0, 1)) . mb_substr($full, 1) : null)
                ->setGroup($group['code'], $group['name'], $group['color'])
                ->setLegislature($legislature)->setDepositedAt($deposit)
                ->setSourceUrl(empty($doc['classification']['sousType']) ? sprintf(self::TEXT_URL, $legislature, strtolower(substr($uid, 8))) : sprintf(self::OPENDATA_URL, $uid));
            if ($isNew || null === $p->getTheme()) {
                $p->setTheme(ThemeGuesser::guess($title . ' ' . $full));
            }
            if ($isNew) {
                $this->em->persist($p);
            }
            ++$stats[$isNew ? 'created' : 'updated'];
            if (0 === ++$n % 200) {
                $this->em->flush();
            }
            if (null !== $limit && $n >= $limit) {
                break;
            }
        }
        $this->em->flush();

        return $stats;
    }

    /** @return array<string,array{code:string,name:string,color:?string}> organeRef => groupe politique de la législature */
    private function loadGroups(string $dir, int $legislature): array
    {
        $out = [];
        foreach (glob(rtrim($dir, '/') . '/*.json') ?: [] as $f) {
            $o = json_decode((string) file_get_contents($f), true)['organe'] ?? null;
            if (is_array($o) && 'GP' === ($o['codeType'] ?? null) && (int) ($o['legislature'] ?? 0) === $legislature) {
                $out[(string) $o['uid']] = ['code' => (string) ($o['libelleAbrege'] ?? $o['uid']), 'name' => (string) ($o['libelle'] ?? ''), 'color' => $o['couleurAssociee'] ?? null];
            }
        }

        return $out;
    }

    /** @return array<string,list<array{from:string,to:?string,organe:string}>> acteurRef => mandats de groupe */
    private function loadMandates(string $dir, int $legislature, array $groups): array
    {
        $out = [];
        foreach (glob(rtrim($dir, '/') . '/*.json') ?: [] as $f) {
            $a = json_decode((string) file_get_contents($f), true)['acteur'] ?? null;
            if (!is_array($a)) {
                continue;
            }
            $list = $a['mandats']['mandat'] ?? [];
            $list = isset($list['uid']) ? [$list] : $list;
            foreach ($list as $m) {
                $ref = $m['organes']['organeRef'] ?? null;
                if ('GP' === ($m['typeOrgane'] ?? null) && (int) ($m['legislature'] ?? 0) === $legislature && is_string($ref) && isset($groups[$ref])) {
                    $id = is_array($a['uid']) ? (string) ($a['uid']['#text'] ?? '') : (string) $a['uid'];
                    $out[$id][] = ['from' => (string) ($m['dateDebut'] ?? '0000-00-00'), 'to' => $m['dateFin'] ?? null, 'organe' => $ref];
                }
            }
        }

        return $out;
    }

    /** @return array{code:string,name:string,color:?string}|null */
    private function resolveGroup(mixed $auteur, ?\DateTimeImmutable $deposit, array $mandates, array $groups): ?array
    {
        if (!is_array($auteur)) {
            return null;
        }
        $first = isset($auteur[0]) ? $auteur[0] : $auteur;
        if (isset($first['organe']['organeRef']) && isset($groups[$first['organe']['organeRef']])) {
            return $groups[$first['organe']['organeRef']];
        }
        $ref = $first['acteur']['acteurRef'] ?? null;
        if (!is_string($ref) || !isset($mandates[$ref]) || null === $deposit) {
            return null;
        }
        $day = $deposit->format('Y-m-d');
        foreach ($mandates[$ref] as $m) {
            if ($m['from'] <= $day && (null === $m['to'] || $day <= $m['to'])) {
                return $groups[$m['organe']];
            }
        }

        return null;
    }

    private function date(mixed $v): ?\DateTimeImmutable
    {
        try {
            return is_string($v) && '' !== $v ? new \DateTimeImmutable(substr($v, 0, 10)) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function clean(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }
}
