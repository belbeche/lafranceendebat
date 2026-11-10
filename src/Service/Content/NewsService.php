<?php

namespace App\Service\Content;

use App\Entity\NewsItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Création et import d'actualités. Trois entrées, un seul chemin :
 *  - fromText() : texte collé tel quel (première ligne = titre, le reste = résumé et texte) ;
 *  - fromArray() : une entrée structurée (formulaire, JSON, CSV) ;
 *  - importJson() : un fichier JSON contenant une liste d'entrées.
 * Tout arrive en brouillon : la publication est un geste volontaire (et exige une source https).
 */
final class NewsService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** Texte collé : titre sur la première ligne, 1er paragraphe = résumé, tout le reste = texte complet. */
    public function fromText(string $text, string $sourceName = '', ?string $sourceUrl = null, ?\DateTimeImmutable $on = null): NewsItem
    {
        $text = trim(preg_replace("/\r\n?/", "\n", $text) ?? $text);
        $paras = preg_split("/\n{2,}/", $text) ?: [];
        $lines = preg_split("/\n/", array_shift($paras) ?? '') ?: [];
        $title = trim((string) array_shift($lines));
        $first = trim(implode(' ', $lines));
        if ('' === $first && $paras) {
            $first = trim((string) array_shift($paras));
        }
        $body = $paras ? implode("\n\n", array_merge([$first], $paras)) : null;

        return $this->fromArray(['title' => rtrim($title, " .:"), 'summary' => $first, 'body' => $body, 'source_name' => $sourceName, 'source_url' => $sourceUrl, 'date' => $on?->format('Y-m-d')]);
    }

    /** @param array<string,mixed> $d clés : title, summary, body, source_name, source_url, date (AAAA-MM-JJ) */
    public function fromArray(array $d): NewsItem
    {
        $n = (new NewsItem())->setTitle((string) ($d['title'] ?? ''))->setSummary((string) ($d['summary'] ?? ''))->setBody($d['body'] ?? null)
            ->setSourceName((string) ($d['source_name'] ?? ''))->setSourceUrl($d['source_url'] ?? null);
        if (!empty($d['date']) && ($dt = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $d['date']))) {
            $n->setPublishedOn($dt);
        }
        $n->setSlug($this->uniqueSlug($n->getTitle()));

        return $n;
    }

    /**
     * @return array{created:int,skipped:int,errors:list<string>}
     */
    public function importJson(string $json, bool $publish = false): array
    {
        $r = ['created' => 0, 'skipped' => 0, 'errors' => []];
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $r['errors'][] = 'Fichier JSON illisible.';

            return $r;
        }
        $items = isset($data['title']) ? [$data] : ($data['items'] ?? $data);
        foreach ($items as $i => $d) {
            if (!is_array($d) || mb_strlen((string) ($d['title'] ?? '')) < 8) {
                $r['errors'][] = 'Entrée ' . ($i + 1) . ' : titre manquant ou trop court.';
                continue;
            }
            if ($this->em->getRepository(NewsItem::class)->findOneBy(['title' => mb_substr(trim(strip_tags((string) $d['title'])), 0, 180)])) {
                ++$r['skipped'];
                continue;
            }
            $n = $this->fromArray($d);
            if ($publish && ($p = $n->publish())) {
                $r['errors'][] = '« ' . mb_substr($n->getTitle(), 0, 50) . ' » reste en brouillon : ' . $p;
            }
            $this->em->persist($n);
            ++$r['created'];
        }
        $this->em->flush();

        return $r;
    }

    public function uniqueSlug(string $title): string
    {
        $base = mb_substr(strtolower((new AsciiSlugger())->slug($title)->toString()), 0, 150) ?: 'actualite';
        $slug = $base;
        $n = 1;
        while ($this->em->getRepository(NewsItem::class)->findOneBy(['slug' => $slug])) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }
}
