<?php

namespace App\Service;

use App\Entity\ActorContact;
use App\Entity\NewsletterSubscriber;
use Doctrine\ORM\EntityManagerInterface;

/** Import CSV d'acteurs publics pour un demarchage professionnel. Refuse tout ce qui ressemble a une adresse personnelle. */
class ActorImporter
{
    public const MAX_ROWS = 2000;
    public const MAX_BYTES = 1048576;
    /** Messageries grand public : une adresse ici est personnelle, donc hors du cadre de l'interet legitime B2B. */
    private const PERSONAL_DOMAINS = ['gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.fr', 'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr', 'live.com', 'live.fr', 'msn.com', 'icloud.com', 'me.com', 'orange.fr', 'wanadoo.fr', 'free.fr', 'sfr.fr', 'neuf.fr', 'laposte.net', 'bbox.fr', 'gmx.com', 'gmx.fr', 'proton.me', 'protonmail.com', 'aol.com', 'yandex.com', 'mail.com'];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return array{added:int,skipped:int,rejected:list<array{line:int,reason:string}>} */
    public function import(string $path): array
    {
        $out = ['added' => 0, 'skipped' => 0, 'rejected' => []];
        $h = fopen($path, 'rb');
        if (!$h) {
            throw new \RuntimeException('Fichier illisible.');
        }
        $first = (string) fgets($h);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $cols = array_map(static fn ($c) => self::norm($c), str_getcsv($first, $delim));
        foreach (['email', 'organisation', 'source_url'] as $need) {
            if (!in_array($need, $cols, true)) {
                fclose($h);
                throw new \InvalidArgumentException("Colonne obligatoire absente : $need. En-têtes attendus : email, organisation, fonction, categorie, source_url.");
            }
        }
        $repo = $this->em->getRepository(ActorContact::class);
        $subs = $this->em->getRepository(NewsletterSubscriber::class);
        $seen = [];
        $line = 1;
        while (($row = fgetcsv($h, 4000, $delim)) !== false) {
            ++$line;
            if ($line - 1 > self::MAX_ROWS) {
                $out['rejected'][] = ['line' => $line, 'reason' => 'Limite de ' . self::MAX_ROWS . ' lignes par fichier atteinte : le reste est ignoré.'];
                break;
            }
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }
            $r = [];
            foreach ($cols as $i => $c) {
                $r[$c] = trim((string) ($row[$i] ?? ''));
            }
            $email = mb_strtolower($r['email'] ?? '');
            $reason = $this->check($email, $r);
            if ($reason) {
                $out['rejected'][] = ['line' => $line, 'reason' => $reason];
                continue;
            }
            if (isset($seen[$email]) || $repo->findOneBy(['email' => $email])) {
                ++$out['skipped'];
                continue;
            }
            // Liste de suppression : un abonne de la lettre desinscrit, en rebond ou en plainte n'est jamais demarche.
            $sub = $subs->findOneBy(['email' => $email]);
            if ($sub && ($sub->getUnsubscribedAt() || $sub->getBouncedAt() || $sub->getMarkedAsSpamAt())) {
                $out['rejected'][] = ['line' => $line, 'reason' => 'Cette adresse s\'est désinscrite ou a rebondi : elle est exclue.'];
                continue;
            }
            $seen[$email] = true;
            $a = new ActorContact($email, $r['organisation'], $r['source_url']);
            $a->setCoordinates($r['telephone'] ?? null, $r['adresse'] ?? null, $r['code_postal'] ?? null, $r['ville'] ?? null, $r['site_web'] ?? null);
            $a->setRole($r['fonction'] ?? null)->setCategory(self::category($r['categorie'] ?? ''))->setLocale(strtolower(trim($r['langue'] ?? '')) ?: null);
            $d = trim($r['consentement'] ?? '');
            $proof = trim($r['preuve_consentement'] ?? '');
            if ('' !== $d || '' !== $proof) {
                $when = \DateTimeImmutable::createFromFormat('!Y-m-d', $d);
                if (!$when || $when > new \DateTimeImmutable() || mb_strlen($proof) < 10) {
                    $out['rejected'][] = ['line' => $line, 'reason' => 'Consentement : date AAAA-MM-JJ passée et preuve (10 caractères minimum) requises ensemble.'];
                    continue;
                }
                $a->recordConsent($when, $proof);
            }
            $this->em->persist($a);
            ++$out['added'];
            if ($out['added'] % 200 === 0) {
                $this->em->flush();
            }
        }
        fclose($h);
        $this->em->flush();

        return $out;
    }

    /**
     * Ajoute un contact unique (utilise par l'import depuis une API publique). Memes garde-fous que le CSV.
     *
     * @return 'added'|'skipped'|string 'added', 'skipped' ou la raison du refus
     */
    private array $pending = [];

    public function addOne(array $r): string
    {
        $email = mb_strtolower(trim((string) ($r['email'] ?? '')));
        if ($reason = $this->check($email, $r)) {
            return $reason;
        }
        if (isset($this->pending[$email]) || $this->em->getRepository(ActorContact::class)->findOneBy(['email' => $email])) {
            return 'skipped';
        }
        $sub = $this->em->getRepository(NewsletterSubscriber::class)->findOneBy(['email' => $email]);
        if ($sub && ($sub->getUnsubscribedAt() || $sub->getBouncedAt() || $sub->getMarkedAsSpamAt())) {
            return 'Adresse desinscrite ou en rebond : exclue.';
        }
        $a = new ActorContact($email, mb_substr($r['organisation'], 0, 190), $r['source_url']);
        $a->setRole($r['fonction'] ?? null)->setCategory(self::category($r['categorie'] ?? ''));
        $a->setCoordinates($r['telephone'] ?? null, $r['adresse'] ?? null, $r['code_postal'] ?? null, $r['ville'] ?? null, $r['site_web'] ?? null);
        $this->em->persist($a);
        $this->pending[$email] = true;

        return 'added';
    }

    public function check(string $email, array $r): ?string
    {
        if (strlen($email) > 180 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Adresse invalide.';
        }
        $domain = substr(strrchr($email, '@'), 1);
        if (!($r['official'] ?? false) && in_array($domain, self::PERSONAL_DOMAINS, true)) {
            return 'Messagerie grand public : adresse personnelle refusée. Seules les adresses professionnelles publiées sont admises.';
        }
        if ('' === ($r['organisation'] ?? '')) {
            return 'Organisation manquante.';
        }
        $src = $r['source_url'] ?? '';
        if (!preg_match('#^https://[^\s/]+\.[^\s/]+/?\S*$#i', $src)) {
            return 'source_url manquante ou invalide : indiquez la page publique (https) où l\'adresse est publiée.';
        }

        return null;
    }

    private static function norm(string $c): string
    {
        $c = mb_strtolower(trim($c));
        $c = strtr($c, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', ' ' => '_', '-' => '_']);

        return match ($c) {
            'mail', 'courriel', 'e_mail' => 'email',
            'structure', 'nom' => 'organisation',
            'role', 'poste' => 'fonction',
            'type' => 'categorie',
            'langue', 'language', 'lang' => 'langue',
            'consent', 'consentement', 'date_consentement' => 'consentement',
            'preuve', 'preuve_consentement' => 'preuve_consentement',
            'source', 'url', 'source_url' => 'source_url',
            'tel', 'telephone', 'phone' => 'telephone',
            'site', 'site_web', 'website' => 'site_web',
            'cp', 'code_postal' => 'code_postal',
            'commune' => 'ville',
            default => $c,
        };
    }

    private static function category(string $v): string
    {
        $v = mb_strtolower($v);

        return isset(ActorContact::CATEGORIES[$v]) ? $v : 'autre';
    }
}
