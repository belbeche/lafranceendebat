<?php

namespace App\Service\Agenda;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Importe l'agenda national depuis des sources officielles, verifie chaque evenement, puis le publie (ou le met en attente).
 * Sources : calendrier.api.gouv.fr (jours feries), config/evenements_nationaux.yaml (journees a date fixe), flux ICS officiels (AGENDA_ICS_FEEDS).
 * Idempotent : relancer le meme jour ne cree aucun doublon. A planifier chaque nuit (voir docs).
 */
class NationalEventImporter
{
    public const API_FERIES = 'https://calendrier.api.gouv.fr/jours-feries/metropole/%d.json';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $http,
        private readonly EventVerifier $verifier,
        private readonly LoggerInterface $logger,
        private readonly string $icsFeeds,
        private readonly string $nationalFile,
        private readonly string $reportFile,
    ) {
    }

    /** @return array{created:int,updated:int,unchanged:int,rejected:list<string>,errors:list<string>,at:string,dry:bool} */
    public function run(bool $dryRun = false, int $years = 2): array
    {
        $r = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'rejected' => [], 'errors' => [], 'at' => date('c'), 'dry' => $dryRun];
        $year = (int) date('Y');
        $candidates = [];
        for ($y = $year; $y < $year + max(1, $years); ++$y) {
            try {
                foreach ($this->feries($y) as $c) {
                    $candidates[] = $c;
                }
            } catch (\Throwable $e) {
                $r['errors'][] = 'Jours fériés ' . $y . ' : ' . $e->getMessage();
                $this->logger->warning('Import agenda : jours feries ' . $y . ' : ' . $e->getMessage());
            }
            foreach ($this->fixed($y) as $c) {
                $candidates[] = $c;
            }
        }
        foreach (array_filter(array_map('trim', explode(',', $this->icsFeeds))) as $url) {
            try {
                foreach ($this->ics($url) as $c) {
                    $candidates[] = $c;
                }
            } catch (\Throwable $e) {
                $r['errors'][] = 'Flux ' . (parse_url($url, PHP_URL_HOST) ?: 'invalide') . ' : ' . $e->getMessage();
            }
        }

        $repo = $this->em->getRepository(Event::class);
        foreach ($candidates as $c) {
            $problems = $this->verifier->problems($c['name'], $c['date'], $c['link'], $c['description']);
            if ($problems) {
                // Les dates passees depuis plus d'un jour sont simplement ignorees, sans bruit.
                if (!str_contains(implode(' ', $problems), 'fenêtre')) {
                    $r['rejected'][] = $c['name'] . ' : ' . implode(', ', $problems);
                }
                continue;
            }
            $e = $repo->findOneBy(['source' => $c['source'], 'externalId' => $c['id']]);
            if ($e) {
                if ($e->getName() === $c['name'] && $e->getDate()->format('Y-m-d H:i') === $c['date']->format('Y-m-d H:i')) {
                    ++$r['unchanged'];
                    continue;
                }
                $e->setName($c['name'])->setDate(\DateTime::createFromInterface($c['date']))->setDescription($c['description'])->setLocation($c['location'])->setLink($c['link'])->markImported($c['source'], $c['id']);
                ++$r['updated'];
                continue;
            }
            // Meme evenement deja saisi a la main : on ne le double pas.
            $dup = $repo->createQueryBuilder('x')->andWhere('x.name = :n')->andWhere('x.date = :d')->setParameter('n', $c['name'])->setParameter('d', \DateTime::createFromInterface($c['date']))->setMaxResults(1)->getQuery()->getOneOrNullResult();
            if ($dup) {
                ++$r['unchanged'];
                continue;
            }
            $e = (new Event())->setName($c['name'])->setDate(\DateTime::createFromInterface($c['date']))->setDescription($c['description'])->setKind($c['kind'])
                ->setLocation($c['location'])->setLink($c['link'])->setStatus($c['trusted'] ? 'upcoming' : 'pending')->markImported($c['source'], $c['id']);
            $this->em->persist($e);
            ++$r['created'];
        }
        if (!$dryRun) {
            $this->em->flush();
            @file_put_contents($this->reportFile, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return $r;
    }

    public function lastReport(): ?array
    {
        $j = @file_get_contents($this->reportFile);

        return $j ? (json_decode($j, true) ?: null) : null;
    }

    /** @return iterable<array<string,mixed>> */
    private function feries(int $year): iterable
    {
        $resp = $this->http->request('GET', sprintf(self::API_FERIES, $year), ['timeout' => 15, 'max_redirects' => 0]);
        $data = $resp->toArray();
        foreach ($data as $day => $label) {
            if (!is_string($label) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
                continue;
            }
            yield $this->candidate('feries', (string) $day, 'Jour férié : ' . $label, 'ferie', 'Jour férié en France métropolitaine. Source : calendrier.api.gouv.fr (données officielles).', 'https://calendrier.api.gouv.fr/jours-feries/', true);
        }
    }

    /** @return iterable<array<string,mixed>> */
    private function fixed(int $year): iterable
    {
        $conf = is_file($this->nationalFile) ? Yaml::parseFile($this->nationalFile) : [];
        foreach ($conf['evenements'] ?? [] as $e) {
            if (!isset($e['mois'], $e['jour'], $e['nom']) || !checkdate((int) $e['mois'], (int) $e['jour'], $year)) {
                continue;
            }
            yield $this->candidate('fixe', sprintf('%04d-%02d-%02d', $year, $e['mois'], $e['jour']), (string) $e['nom'], 'national', (string) ($e['description'] ?? '') . ' Source : ' . ($e['source'] ?? ''), $e['source'] ?? null, true);
        }
    }

    /** @return iterable<array<string,mixed>> */
    private function ics(string $url): iterable
    {
        if (!EventVerifier::hostAllowed($url)) {
            throw new \RuntimeException('hôte non autorisé');
        }
        $resp = $this->http->request('GET', $url, ['timeout' => 20, 'max_redirects' => 0, 'headers' => ['Accept' => 'text/calendar']]);
        $body = $resp->getContent();
        if (strlen($body) > 2_000_000) {
            throw new \RuntimeException('flux trop volumineux');
        }
        $body = preg_replace("/\r?\n[ \t]/", '', $body) ?? '';
        $host = (string) parse_url($url, PHP_URL_HOST);
        $n = 0;
        foreach (preg_split('/BEGIN:VEVENT/', $body) ?: [] as $i => $blk) {
            if (0 === $i || ++$n > 500) {
                continue;
            }
            $f = static function (string $k) use ($blk): ?string {
                return preg_match('/^' . $k . '(?:;[^:\r\n]*)?:(.*)$/mi', $blk, $m) ? trim(str_replace(['\\n', '\\,', '\;', '\\\\'], [' ', ',', ';', '\\'], $m[1])) : null;
            };
            $start = $f('DTSTART');
            $uid = $f('UID');
            $sum = $f('SUMMARY');
            if (!$start || !$uid || !$sum || !preg_match('/^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2})(\d{2})(Z)?)?$/', $start, $m)) {
                continue;
            }
            $date = new \DateTimeImmutable(sprintf('%s-%s-%s %s:%s:00', $m[1], $m[2], $m[3], $m[4] ?? '00', $m[5] ?? '00'), new \DateTimeZone(isset($m[7]) ? 'UTC' : 'Europe/Paris'));
            $date = $date->setTimezone(new \DateTimeZone('Europe/Paris'));
            $link = $f('URL');
            yield ['source' => 'ics:' . substr(md5($url), 0, 8), 'id' => mb_substr($uid, 0, 190), 'name' => mb_substr($sum, 0, 200), 'date' => $date, 'kind' => 'institution',
                'description' => mb_substr((string) $f('DESCRIPTION'), 0, 1000) . ' Source : ' . $host, 'location' => ($l = $f('LOCATION')) ? mb_substr($l, 0, 255) : null,
                'link' => $link ?: $url, 'trusted' => true];
        }
    }

    /** @return array<string,mixed> */
    private function candidate(string $source, string $day, string $name, string $kind, string $description, ?string $link, bool $trusted): array
    {
        return ['source' => $source, 'id' => $day . ':' . mb_substr(md5($name), 0, 10), 'name' => $name, 'date' => new \DateTimeImmutable($day . ' 00:00:00', new \DateTimeZone('Europe/Paris')),
            'kind' => $kind, 'description' => $description, 'location' => 'France', 'link' => $link, 'trusted' => $trusted];
    }
}
