<?php

// src/Service/Cache/CacheService.php
namespace App\Service\Cache;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Vide les caches applicatifs liés aux abonnés et aux campagnes après une modification.
 * Ajouter une méthode ici quand un nouvel écran met des listes en cache, plutôt que de vider le cache entier.
 */
class CacheService
{
    public function __construct(
        private TagAwareCacheInterface $cache
    ) {}
    
    public function clearSubscribersCache(): void
    {
        $this->cache->invalidateTags(['subscribers', 'active_subscribers']);
    }
    
    public function clearCampaignsCache(): void
    {
        $this->cache->invalidateTags(['campaigns', 'previous_campaigns']);
    }
}