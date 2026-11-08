<?php

// src/Service/CampaignTrackingService.php

namespace App\Service;

use App\Entity\CampaignTracking;
use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Service métier « CampaignTrackingService ».
 */
class CampaignTrackingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $projectDir
    ) {
    }

    public function trackOpen(
        NewsletterCampaign $campaign,
        ?NewsletterSubscriber $subscriber = null,
        Request $request = null
    ): CampaignTracking {
        $tracking = new CampaignTracking();
        $tracking->setCampaign($campaign);
        $tracking->setSubscriber($subscriber);
        $tracking->setAction('open');
        
        if ($request) {
            $this->addRequestInfo($tracking, $request);
        }
        
        $this->entityManager->persist($tracking);
        
        // Mettre à jour la campagne
        $campaign->trackOpen($subscriber);
        
        // Mettre à jour le subscriber
        if ($subscriber) {
            $subscriber->incrementTotalOpens();
        }
        
        $this->entityManager->flush();
        
        return $tracking;
    }

    public function trackClick(
        NewsletterCampaign $campaign,
        string $url,
        ?NewsletterSubscriber $subscriber = null,
        Request $request = null
    ): CampaignTracking {
        $tracking = new CampaignTracking();
        $tracking->setCampaign($campaign);
        $tracking->setSubscriber($subscriber);
        $tracking->setAction('click');
        $tracking->setUrl($url);
        
        if ($request) {
            $this->addRequestInfo($tracking, $request);
        }
        
        $this->entityManager->persist($tracking);
        
        // Mettre à jour la campagne
        $campaign->trackClick($subscriber, $url);
        
        // Mettre à jour le subscriber
        if ($subscriber) {
            $subscriber->incrementTotalClicks();
        }
        
        $this->entityManager->flush();
        
        return $tracking;
    }

    public function trackBounce(
        NewsletterCampaign $campaign,
        string $email,
        string $type = 'hard',
        ?string $reason = null
    ): void {
        // Chercher le subscriber par email
        $subscriber = $this->entityManager->getRepository(NewsletterSubscriber::class)
            ->findOneBy(['email' => $email]);
        
        $tracking = new CampaignTracking();
        $tracking->setCampaign($campaign);
        $tracking->setSubscriber($subscriber);
        $tracking->setAction('bounce');
        $tracking->setBounceType($type);
        $tracking->setBounceReason($reason);
        
        $this->entityManager->persist($tracking);
        
        // Mettre à jour la campagne
        $campaign->trackBounce($subscriber, $type);
        
        $this->entityManager->flush();
        
        // Loguer le bounce dans un fichier
        $this->logBounce($campaign, $email, $type, $reason);
    }

    public function trackUnsubscribe(
        NewsletterCampaign $campaign,
        NewsletterSubscriber $subscriber,
        Request $request = null
    ): CampaignTracking {
        $tracking = new CampaignTracking();
        $tracking->setCampaign($campaign);
        $tracking->setSubscriber($subscriber);
        $tracking->setAction('unsubscribe');
        
        if ($request) {
            $this->addRequestInfo($tracking, $request);
        }
        
        $this->entityManager->persist($tracking);
        
        // Mettre à jour la campagne
        $campaign->trackUnsubscribe($subscriber);
        
        $this->entityManager->flush();
        
        return $tracking;
    }

    public function trackSpamReport(
        NewsletterCampaign $campaign,
        NewsletterSubscriber $subscriber,
        Request $request = null
    ): CampaignTracking {
        $tracking = new CampaignTracking();
        $tracking->setCampaign($campaign);
        $tracking->setSubscriber($subscriber);
        $tracking->setAction('spam');
        
        if ($request) {
            $this->addRequestInfo($tracking, $request);
        }
        
        $this->entityManager->persist($tracking);
        
        // Mettre à jour la campagne
        $campaign->trackSpamReport($subscriber);
        
        $this->entityManager->flush();
        
        return $tracking;
    }

    private function addRequestInfo(CampaignTracking $tracking, Request $request): void
    {
        $tracking->setIpAddress($request->getClientIp());
        $tracking->setUserAgent($request->headers->get('User-Agent'));
        
        // Déterminer le type d'appareil
        $userAgent = $request->headers->get('User-Agent');
        $deviceType = 'desktop';
        
        if (preg_match('/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i', $userAgent)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/(tablet|ipad|playbook|silk)/i', $userAgent)) {
            $deviceType = 'tablet';
        }
        
        $tracking->setDeviceType($deviceType);
    }

    private function logBounce(
        NewsletterCampaign $campaign,
        string $email,
        string $type,
        ?string $reason
    ): void {
        $logDir = $this->projectDir . '/var/log/bounces/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . date('Y-m-d') . '.log';
        $logMessage = sprintf(
            "[%s] Campaign #%d - %s - %s bounce: %s - Reason: %s\n",
            date('Y-m-d H:i:s'),
            $campaign->getId(),
            $email,
            $type,
            $campaign->getSubject(),
            $reason ?? 'N/A'
        );
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    public function getCampaignStats(NewsletterCampaign $campaign): array
    {
        $trackings = $this->entityManager->getRepository(CampaignTracking::class)
            ->findBy(['campaign' => $campaign]);
        
        $stats = [
            'total_trackings' => count($trackings),
            'opens_by_hour' => [],
            'clicks_by_hour' => [],
            'top_links' => [],
            'devices' => [
                'desktop' => 0,
                'mobile' => 0,
                'tablet' => 0,
            ],
            'locations' => [],
        ];
        
        foreach ($trackings as $tracking) {
            $hour = $tracking->getCreatedAt()->format('Y-m-d H');
            
            // Compter par type d'action
            if ($tracking->getAction() === 'open') {
                if (!isset($stats['opens_by_hour'][$hour])) {
                    $stats['opens_by_hour'][$hour] = 0;
                }
                $stats['opens_by_hour'][$hour]++;
            } elseif ($tracking->getAction() === 'click') {
                if (!isset($stats['clicks_by_hour'][$hour])) {
                    $stats['clicks_by_hour'][$hour] = 0;
                }
                $stats['clicks_by_hour'][$hour]++;
                
                // Top liens
                $url = $tracking->getUrl();
                if ($url) {
                    if (!isset($stats['top_links'][$url])) {
                        $stats['top_links'][$url] = 0;
                    }
                    $stats['top_links'][$url]++;
                }
            }
            
            // Devices
            $device = $tracking->getDeviceType();
            if ($device && isset($stats['devices'][$device])) {
                $stats['devices'][$device]++;
            }
        }
        
        // Trier les top liens
        arsort($stats['top_links']);
        $stats['top_links'] = array_slice($stats['top_links'], 0, 10, true);
        
        return $stats;
    }
}