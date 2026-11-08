<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Numéro de version des CSS : dernière date de modification d'un fichier de public/css. Change tout seul à chaque livraison. */
class AssetVersionExtension extends AbstractExtension
{
    private ?string $version = null;

    public function __construct(private readonly string $projectDir) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('asset_version', $this->version(...)), new TwigFunction('local_or_cdn', $this->localOrCdn(...))];
    }

    /** Fichier hébergé sur le site s'il est présent, sinon repli sur le CDN : le style ne casse jamais si un envoi est incomplet. */
    public function localOrCdn(string $publicPath, string $cdnUrl): string
    {
        return is_readable($this->projectDir . '/public/' . ltrim($publicPath, '/')) ? '/' . ltrim($publicPath, '/') . '?v=' . $this->version() : $cdnUrl;
    }

    public function version(): string
    {
        if (null === $this->version) {
            clearstatcache();
            $max = 0;
            foreach (glob($this->projectDir . '/public/css/*.css') ?: [] as $f) {
                $max = max($max, (int) @filemtime($f));
            }
            $this->version = $max ? dechex($max) : '1';
        }

        return $this->version;
    }
}
