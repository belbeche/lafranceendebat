<?php

namespace App\Service;

/**
 * Traduit le HTML rendu (texte visible et attributs accessibles) a partir d'un catalogue
 * "texte francais => traduction" par langue : translations/ui/<langue>.json.
 * Les contenus saisis par les utilisateurs ne figurent pas au catalogue : ils restent tels quels.
 * Les nombres sont remplaces par {#1}, {#2}... dans les cles et dans les traductions.
 */
class HtmlTranslator
{
    /** @var array<string, array<string,string>> */
    private array $catalogues = [];

    public function __construct(private readonly string $projectDir)
    {
    }

    /** Langue du catalogue a utiliser : la langue demandee, sinon l'anglais. */
    public function catalogueLocale(string $locale): ?string
    {
        foreach ([$locale, 'en'] as $l) {
            if ($l !== 'fr' && is_file($this->path($l))) {
                return $l;
            }
        }

        return null;
    }

    public function translate(string $html, string $locale): string
    {
        $use = $this->catalogueLocale($locale);
        if ($use === null) {
            return $html;
        }
        $cat = $this->catalogue($use);
        $parts = preg_split('~(<script\b.*?</script>|<style\b.*?</style>|<!--.*?-->|<[^>]+>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '<') {
                if (!preg_match('~^<(script|style|!--)~i', $part)) {
                    $parts[$i] = $this->translateTag($part, $cat);
                }
                continue;
            }
            $parts[$i] = $this->translateText($part, $cat);
        }

        return implode('', $parts);
    }

    /** @return string[] cles de tous les fragments traduisibles d'un HTML (texte et attributs) */
    public function fragments(string $html): array
    {
        $out = [];
        $parts = preg_split('~(<script\b.*?</script>|<style\b.*?</style>|<!--.*?-->|<[^>]+>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($parts as $part) {
            if ($part === '' || preg_match('~^<(script|style|!--)~i', $part)) {
                continue;
            }
            if ($part[0] !== '<') {
                foreach (explode(' | ', $part) as $piece) {
                    if (($k = self::key($piece)) !== null) {
                        $out[$k[0]] = true;
                    }
                }
                continue;
            }
            if (preg_match_all('~\s(?:title|alt|placeholder|aria-label|aria-description|data-confirm)=(["\'])(.*?)\1~is', $part, $mm)) {
                foreach ($mm[2] as $v) {
                    if (($k = self::key($v)) !== null) {
                        $out[$k[0]] = true;
                    }
                }
            }
        }

        return array_keys($out);
    }

    /** Cle de catalogue d'un fragment (et nombres extraits), ou null s'il n'y a rien a traduire. */
    public static function key(string $raw): ?array
    {
        $text = trim(preg_replace('~\s+~u', ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if ($text === '' || !preg_match('~\p{L}{2,}~u', $text)) {
            return null;
        }
        $numbers = [];
        $key = preg_replace_callback('~\d+(?:[.,:/]\d+)*~', function (array $m) use (&$numbers) {
            $numbers[] = $m[0];

            return '{#' . count($numbers) . '}';
        }, $text);

        return [$key, $numbers];
    }

    /** @param array<string,string> $cat */
    private function translateText(string $segment, array $cat): string
    {
        if (!preg_match('~^(\s*)(.*?)(\s*)$~su', $segment, $m)) {
            return $segment;
        }
        if (str_contains($m[2], ' | ')) {
            $pieces = array_map(fn (string $piece) => $this->translateText($piece, $cat), explode(' | ', $m[2]));

            return $m[1] . implode(' | ', $pieces) . $m[3];
        }
        $k = self::key($m[2]);
        if ($k === null || !isset($cat[$k[0]])) {
            return $segment;
        }

        return $m[1] . $this->fill($cat[$k[0]], $k[1]) . $m[3];
    }

    /** @param array<string,string> $cat */
    private function translateTag(string $tag, array $cat): string
    {
        $isMeta = (bool) preg_match('~^<meta\b~i', $tag);
        $names = $isMeta && preg_match('~\b(name|property)=["\'](description|og:title|og:description)["\']~i', $tag)
            ? 'title|alt|placeholder|aria-label|aria-description|data-confirm|content'
            : 'title|alt|placeholder|aria-label|aria-description|data-confirm';
        $tag = preg_replace_callback('~(\s(?:' . $names . ')=)(["\'])(.*?)\2~is', function (array $m) use ($cat) {
            $k = self::key($m[3]);
            if ($k === null || !isset($cat[$k[0]])) {
                return $m[0];
            }

            return $m[1] . $m[2] . htmlspecialchars($this->fill($cat[$k[0]], $k[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $m[2];
        }, $tag) ?? $tag;
        if (preg_match('~^<input\b[^>]*\btype=["\'](submit|button)["\']~i', $tag)) {
            $tag = preg_replace_callback('~(\svalue=)(["\'])(.*?)\2~is', function (array $m) use ($cat) {
                $k = self::key($m[3]);

                return $k !== null && isset($cat[$k[0]])
                    ? $m[1] . $m[2] . htmlspecialchars($this->fill($cat[$k[0]], $k[1]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $m[2]
                    : $m[0];
            }, $tag) ?? $tag;
        }

        return $tag;
    }

    /** @param string[] $numbers */
    private function fill(string $translation, array $numbers): string
    {
        $out = $translation;
        foreach ($numbers as $i => $n) {
            $out = str_replace('{#' . ($i + 1) . '}', $n, $out);
        }

        return htmlspecialchars($out, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @return array<string,string> */
    public function catalogue(string $locale): array
    {
        if (!isset($this->catalogues[$locale])) {
            $data = is_file($this->path($locale)) ? json_decode((string) file_get_contents($this->path($locale)), true) : [];
            $this->catalogues[$locale] = is_array($data) ? $data : [];
        }

        return $this->catalogues[$locale];
    }

    private function path(string $locale): string
    {
        return $this->projectDir . '/translations/ui/' . $locale . '.json';
    }
}
