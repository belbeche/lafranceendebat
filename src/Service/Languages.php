<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Catalogue des langues proposees a l'arrivee.
 * 'ui' = interface traduite (fichier translations/messages.<code>.yaml).
 * Les autres langues s'affichent avec l'interface en anglais et la traduction automatique du navigateur.
 * Pour ajouter une langue complete : ajouter son code ici et creer translations/messages.<code>.yaml.
 */
final class Languages
{
    public const COOKIE = 'lfed_lang';
    public const DEFAULT = 'fr';

    /** @var array<string, array{name:string, rtl:bool, ui:bool}> */
    private const LIST = [
        'fr' => ['Français', false, true], 'en' => ['English', false, true], 'es' => ['Español', false, true],
        'ar' => ['العربية', true, true], 'pt' => ['Português', false, true], 'de' => ['Deutsch', false, true],
        'it' => ['Italiano', false, true], 'tr' => ['Türkçe', false, true], 'ru' => ['Русский', false, true],
        'zh' => ['中文', false, true], 'hi' => ['हिन्दी', false, true], 'ja' => ['日本語', false, true],
        'nl' => ['Nederlands', false, false], 'pl' => ['Polski', false, false], 'ro' => ['Română', false, false],
        'uk' => ['Українська', false, false], 'el' => ['Ελληνικά', false, false], 'cs' => ['Čeština', false, false],
        'sv' => ['Svenska', false, false], 'hu' => ['Magyar', false, false], 'bg' => ['Български', false, false],
        'sr' => ['Српски', false, false], 'hr' => ['Hrvatski', false, false], 'da' => ['Dansk', false, false],
        'fi' => ['Suomi', false, false], 'no' => ['Norsk', false, false], 'ca' => ['Català', false, false],
        'eu' => ['Euskara', false, false], 'br' => ['Brezhoneg', false, false], 'oc' => ['Occitan', false, false],
        'co' => ['Corsu', false, false], 'he' => ['עברית', true, false], 'fa' => ['فارسی', true, false],
        'ur' => ['اردو', true, false], 'ku' => ['Kurdî', false, false], 'am' => ['አማርኛ', false, false],
        'ber' => ['ⵜⴰⵎⴰⵣⵉⵖⵜ', false, false], 'sw' => ['Kiswahili', false, false], 'ha' => ['Hausa', false, false],
        'wo' => ['Wolof', false, false], 'ln' => ['Lingála', false, false], 'mg' => ['Malagasy', false, false],
        'ht' => ['Kreyòl ayisyen', false, false], 'bn' => ['বাংলা', false, false], 'ta' => ['தமிழ்', false, false],
        'te' => ['తెలుగు', false, false], 'mr' => ['मराठी', false, false], 'pa' => ['ਪੰਜਾਬੀ', false, false],
        'th' => ['ไทย', false, false], 'vi' => ['Tiếng Việt', false, false], 'id' => ['Bahasa Indonesia', false, false],
        'ms' => ['Bahasa Melayu', false, false], 'tl' => ['Filipino', false, false], 'ko' => ['한국어', false, false],
        'km' => ['ខ្មែរ', false, false], 'my' => ['မြန်မာ', false, false], 'ne' => ['नेपाली', false, false],
        'si' => ['සිංහල', false, false], 'kk' => ['Қазақша', false, false], 'uz' => ['Oʻzbekcha', false, false],
        'az' => ['Azərbaycanca', false, false], 'hy' => ['Հայերեն', false, false], 'ka' => ['ქართული', false, false],
        'sq' => ['Shqip', false, false], 'mk' => ['Македонски', false, false], 'sk' => ['Slovenčina', false, false],
        'sl' => ['Slovenščina', false, false], 'lt' => ['Lietuvių', false, false], 'lv' => ['Latviešu', false, false],
        'et' => ['Eesti', false, false], 'is' => ['Íslenska', false, false], 'ga' => ['Gaeilge', false, false],
        'cy' => ['Cymraeg', false, false], 'mt' => ['Malti', false, false], 'af' => ['Afrikaans', false, false],
        'zu' => ['isiZulu', false, false], 'so' => ['Soomaali', false, false], 'mn' => ['Монгол', false, false],
    ];

    /** @return array<string, array{name:string, rtl:bool, ui:bool}> */
    public function all(): array
    {
        $out = [];
        foreach (self::LIST as $code => [$name, $rtl, $ui]) {
            $out[$code] = ['name' => $name, 'rtl' => $rtl, 'ui' => $ui];
        }

        return $out;
    }

    public function isSupported(string $code): bool
    {
        return isset(self::LIST[$code]);
    }

    public function isRtl(string $code): bool
    {
        return self::LIST[$code][1] ?? false;
    }

    public function hasUi(string $code): bool
    {
        return self::LIST[$code][2] ?? false;
    }

    public function name(string $code): string
    {
        return self::LIST[$code][0] ?? $code;
    }

    /** Meilleure langue d'apres l'en-tete Accept-Language, ou null. */
    public function negotiate(Request $request): ?string
    {
        foreach ($request->getLanguages() as $candidate) {
            $base = strtolower(explode('_', str_replace('-', '_', $candidate))[0]);
            if (isset(self::LIST[$base])) {
                return $base;
            }
        }

        return null;
    }
}
