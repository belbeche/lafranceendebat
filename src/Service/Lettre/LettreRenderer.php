<?php

namespace App\Service\Lettre;

/** Transforme un texte brut en HTML sur : tout est echappe, seuls les liens https sont rendus cliquables. */
class LettreRenderer
{
    public function toHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = preg_split("/\n{2,}/", trim($text)) ?: [];
        $out = [];
        foreach ($paragraphs as $p) {
            $safe = htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safe = preg_replace_callback('~https://[^\s<>"\']{4,200}~i', static function (array $m): string {
                $url = rtrim($m[0], '.,;:!?)');
                $tail = substr($m[0], strlen($url));

                return '<a href="' . $url . '" rel="noopener noreferrer" style="color:#002395">' . $url . '</a>' . $tail;
            }, $safe);
            $out[] = '<p style="margin:0 0 16px">' . nl2br($safe, false) . '</p>';
        }

        return implode("\n", $out);
    }
}
