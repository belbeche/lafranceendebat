<?php

namespace App\Service\Content;

/**
 * Mini-langage de mise en forme pour les textes écrits depuis l'administration (dossiers, actualités), sans HTML libre.
 * Tout le texte est échappé d'abord, puis seules quelques marques sont transformées : c'est ce qui rend l'édition sûre.
 *
 *   ## Titre            -> titre de section (h2)
 *   ### Sous-titre      -> h3
 *   - élément           -> liste à puces (une ligne par élément)
 *   1. élément          -> liste numérotée
 *   > citation          -> citation
 *   **gras**, *italique*
 *   [texte](https://adresse)  -> lien (https uniquement, ouvert avec rel noopener nofollow)
 *   [texte](/chemin)          -> lien interne au site
 *   ligne vide          -> nouveau paragraphe
 *
 * Pour ajouter une marque : une ligne dans inline() (dans le texte) ou un cas dans render() (bloc entier).
 */
final class SafeMarkdown
{
    public function render(string $text): string
    {
        $lines = preg_split('/\R/', trim($text)) ?: [];
        $out = [];
        $para = [];
        $list = null; // ['ul'|'ol', [items]]
        $flushPara = function () use (&$para, &$out): void {
            if ($para) {
                $out[] = '<p>' . $this->inline(implode(' ', $para)) . '</p>';
                $para = [];
            }
        };
        $flushList = function () use (&$list, &$out): void {
            if ($list) {
                $out[] = '<' . $list[0] . '>' . implode('', array_map(fn ($i) => '<li>' . $this->inline($i) . '</li>', $list[1])) . '</' . $list[0] . '>';
                $list = null;
            }
        };
        foreach ($lines as $raw) {
            $l = rtrim($raw);
            if ('' === trim($l)) {
                $flushPara();
                $flushList();
                continue;
            }
            if (preg_match('/^(#{2,3})\s+(.+)$/u', $l, $m)) {
                $flushPara();
                $flushList();
                $h = 'h' . strlen($m[1]);
                $out[] = "<$h class=\"" . ('h2' === $h ? 'h4 mt-4' : 'h5 mt-3') . "\">" . $this->inline($m[2]) . "</$h>";
            } elseif (preg_match('/^[-*]\s+(.+)$/u', $l, $m)) {
                $flushPara();
                if (!$list || 'ul' !== $list[0]) {
                    $flushList();
                    $list = ['ul', []];
                }
                $list[1][] = $m[1];
            } elseif (preg_match('/^\d+[.)]\s+(.+)$/u', $l, $m)) {
                $flushPara();
                if (!$list || 'ol' !== $list[0]) {
                    $flushList();
                    $list = ['ol', []];
                }
                $list[1][] = $m[1];
            } elseif (preg_match('/^>\s?(.*)$/u', $l, $m)) {
                $flushPara();
                $flushList();
                $out[] = '<blockquote class="carte">' . $this->inline($m[1]) . '</blockquote>';
            } else {
                $flushList();
                $para[] = trim($l);
            }
        }
        $flushPara();
        $flushList();

        return implode("\n", $out);
    }

    /** Texte brut (sans marque) : utile pour un extrait ou une description. */
    public function plain(string $text, int $max = 200): string
    {
        $t = preg_replace('/\[([^\]]+)\]\(https?:\/\/[^)\s]+\)/u', '$1', $text) ?? $text;
        $t = preg_replace('/^(#{2,3}|[-*>]|\d+[.)])\s+/mu', '', $t) ?? $t;
        $t = trim(preg_replace('/[*_]{1,2}/', '', preg_replace('/\s+/', ' ', $t) ?? $t) ?? $t);

        return mb_strlen($t) > $max ? rtrim(mb_substr($t, 0, $max - 1)) . '…' : $t;
    }

    private function inline(string $s): string
    {
        $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // liens : seul https est accepté, le reste reste du texte
        $s = preg_replace_callback('/\[([^\]]+)\]\((https:\/\/[^)\s]+)\)/u', static fn ($m) => '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer nofollow">' . $m[1] . '</a>', $s) ?? $s;
        // liens internes : un chemin du site (/debats/...), jamais //hote ni autre schema
        $s = preg_replace_callback('/\[([^\]]+)\]\((\/(?!\/)[^)\s]*)\)/u', static fn ($m) => '<a href="' . $m[2] . '">' . $m[1] . '</a>', $s) ?? $s;
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '<em>$1</em>', $s) ?? $s;

        return $s;
    }
}
