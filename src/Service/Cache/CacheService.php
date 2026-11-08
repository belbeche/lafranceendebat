<?php

// src/Service/Cache/CacheService.php
namespace App\Service\Cache;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Service métier « CacheService ».
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