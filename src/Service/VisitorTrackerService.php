<?php
// src/Service/VisitorTrackerService.php

namespace App\Service;

use App\Entity\SiteVisitor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Service métier « VisitorTrackerService ».
 */
class VisitorTrackerService
{
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private CacheItemPoolInterface $cache;
    private LoggerInterface $logger;
    private array $botPatterns;
    private bool $isProduction;

    public function __construct(
        EntityManagerInterface $entityManager, 
        RequestStack $requestStack,
        CacheItemPoolInterface $cache,
        LoggerInterface $logger,
        string $kernelEnvironment
    ) {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->isProduction = $kernelEnvironment === 'prod';
        
        // Liste des patterns pour détecter les bots
        $this->botPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'checker', 'scan', 
            'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
            'yandexbot', 'facebookexternalhit', 'twitterbot', 'rogerbot',
            'linkedinbot', 'embedly', 'quora link preview', 'showyoubot',
            'outbrain', 'pinterest', 'slackbot', 'vkShare', 'W3C_Validator',
            'whatsapp', 'telegrambot', 'discordbot', 'redditbot', 'applebot',
            'petalbot', 'ahrefs', 'semrush'
        ];
    }

    public function trackVisit(string $page): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return false;
        }

        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent', '');
        
        // Éviter de tracker les bots
        if ($this->isBot($userAgent)) {
            return false;
        }

        // Éviter les IPs locales et privées
        if ($this->isLocalOrPrivateIp($ip)) {
            return false;
        }

        // Vérifier le cache pour éviter les visites trop rapprochées (10 minutes en prod)
        $cacheDuration = $this->isProduction ? 600 : 300; // 10 min en prod, 5 min en dev
        $cacheKey = 'visit_' . md5($ip . $page);
        
        if ($this->cache->getItem($cacheKey)->isHit()) {
            return false;
        }

        // Mettre en cache
        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set(true);
        $cacheItem->expiresAfter($cacheDuration);
        $this->cache->save($cacheItem);

        try {
            $visitor = new SiteVisitor();
            $visitor->setIpAddress($this->anonymize($ip));
            $visitor->setUserAgent(substr($userAgent, 0, 255)); // Limiter la longueur
            $visitor->setPageVisited(substr($page, 0, 255)); // Limiter la longueur
            
            // Vie privee : adresse IP anonymisee, aucune geolocalisation (aucune IP n'est envoyee a un service tiers).
            $this->entityManager->persist($visitor);
            
            // Utiliser flush() sans transaction pour éviter les blocages
            $this->entityManager->flush();

            return true;

        } catch (\Exception $e) {
            // Logger l'erreur proprement
            $this->logger->error('Visitor tracking error', [
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /** IPv4 : dernier octet mis a zero. IPv6 : conserve les 48 premiers bits. */
    private function anonymize(string $ip): string
    {
        $packed = @inet_pton($ip);
        if (false === $packed) {
            return '0.0.0.0';
        }
        if (4 === strlen($packed)) {
            $packed[3] = "\0";
        } else {
            $packed = substr($packed, 0, 6) . str_repeat("\0", 10);
        }

        return (string) inet_ntop($packed);
    }

    private function isBot(?string $userAgent): bool
    {
        if (empty($userAgent)) {
            return true;
        }

        $userAgent = strtolower($userAgent);

        // Vérification rapide des patterns courants
        foreach ($this->botPatterns as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isLocalOrPrivateIp(string $ip): bool
    {
        // IPs locales
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        // IPs privées
        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12', 
            '192.168.0.0/16',
            '169.254.0.0/16'
        ];

        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        
        // Conversion IP en format binaire
        $ip = @inet_pton($ip);
        $subnet = @inet_pton($subnet);

        if ($ip === false || $subnet === false) {
            return false;
        }

        // Masque de sous-réseau
        $mask = -1 << (128 - $bits);
        if (strlen($ip) == 4) { // IPv4
            $mask = ~((1 << (32 - $bits)) - 1);
        }

        return (substr($ip, 0, intdiv($bits, 8)) == substr($subnet, 0, intdiv($bits, 8)));
    }

    private function addGeolocation(SiteVisitor $visitor, string $ip): void
    {
        try {
            // Pour les IPs locales
            if ($ip === '127.0.0.1' || $this->isLocalOrPrivateIp($ip)) {
                $visitor->setCountryCode('FR');
                $visitor->setCity('Local');
                return;
            }

            // En production, utiliser un service fiable avec fallback
            $geolocationData = $this->getGeolocationFromService($ip);
            
            if ($geolocationData) {
                $visitor->setCountryCode($geolocationData['country_code'] ?? null);
                $visitor->setCity($geolocationData['city'] ?? null);
            } else {
                // Fallback basique
                $visitor->setCountryCode('FR');
                $visitor->setCity('Unknown');
            }

        } catch (\Exception $e) {
            // Logger l'erreur mais continuer
            $this->logger->warning('Geolocation failed', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            
            $visitor->setCountryCode('FR');
            $visitor->setCity('Error');
        }
    }

    private function getGeolocationFromService(string $ip): ?array
    {
        $cacheKey = 'geo_' . md5($ip);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        // Essayer ipapi.co d'abord (gratuit 1000 req/mois)
        $data = $this->callIpApi($ip);
        
        if (!$data) {
            // Fallback sur ipinfo.io
            $data = $this->callIpInfo($ip);
        }

        // Mettre en cache pour 24 heures
        if ($data) {
            $cacheItem->set($data);
            $cacheItem->expiresAfter(86400); // 24 heures
            $this->cache->save($cacheItem);
        }

        return $data;
    }

    private function callIpApi(string $ip): ?array
    {
        try {
            $url = "http://ipapi.co/{$ip}/json/";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'LaOumaApp/1.0'
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                
                if (isset($data['country_code'])) {
                    return [
                        'country_code' => $data['country_code'],
                        'city' => $data['city'] ?? null
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('ipapi.co service failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return null;
    }

    private function callIpInfo(string $ip): ?array
    {
        try {
            $url = "http://ipinfo.io/{$ip}/json";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'LaOumaApp/1.0'
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                
                if (isset($data['country'])) {
                    return [
                        'country_code' => $data['country'],
                        'city' => $data['city'] ?? null
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->debug('ipinfo.io service failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Récupère les statistiques de visites pour le dashboard
     */
    public function getDashboardStats(): array
    {
        try {
            $today = new \DateTimeImmutable('today');
            $yesterday = $today->modify('-1 day');
            $lastWeek = $today->modify('-7 days');
            $lastMonth = $today->modify('-30 days');

            $repository = $this->entityManager->getRepository(SiteVisitor::class);

            return [
                'today' => $repository->getVisitorsCountSince($today),
                'yesterday' => $repository->getVisitorsCountSince($yesterday, $today),
                'last_week' => $repository->getVisitorsCountSince($lastWeek),
                'last_month' => $repository->getVisitorsCountSince($lastMonth),
                'total' => $repository->getTotalUniqueVisitors(),
                'last_30_days' => $repository->getVisitorsLast30Days(),
                'popular_pages' => $repository->getPopularPages(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get dashboard stats', ['error' => $e->getMessage()]);
            
            return [
                'today' => 0,
                'yesterday' => 0,
                'last_week' => 0,
                'last_month' => 0,
                'total' => 0,
                'last_30_days' => [],
                'popular_pages' => [],
            ];
        }
    }

    /**
     * Nettoie les anciennes visites (plus de 90 jours)
     */
    public function cleanupOldVisits(): int
    {
        try {
            return $this->entityManager->getRepository(SiteVisitor::class)
                ->cleanupOldVisits(90);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup old visits', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Statistiques de performance du service
     */
    public function getServiceStats(): array
    {
        $repository = $this->entityManager->getRepository(SiteVisitor::class);
        
        return [
            'total_visits' => $repository->count([]),
            'unique_visitors' => $repository->getTotalUniqueVisitors(),
            'environment' => $this->isProduction ? 'production' : 'development',
            'cache_system' => get_class($this->cache),
        ];
    }
}