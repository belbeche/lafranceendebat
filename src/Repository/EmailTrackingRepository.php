<?php

namespace App\Repository;

use App\Entity\EmailTracking;
use App\Entity\NewsletterCampaign;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailTracking>
 */
class EmailTrackingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTracking::class);
    }

    private function getEmptyStats(): array
    {
        return [
            'basic_stats' => [
                'total' => 0,
                'opens' => 0,
                'clicks' => 0,
                'bounces' => 0,
            ],
            'opens_by_hour' => [],
            'clicks_by_hour' => [],
            'bounce_types' => [],
            'devices' => [],
            'top_links' => [],
            'open_rate' => 0,
            'click_rate' => 0,
            'bounce_rate' => 0,
        ];
    }

    /**
     * Récupère les statistiques détaillées pour une campagne
     */
    public function getDetailedStats(int $campaignId): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        // Statistiques d'ouvertures
        $openStats = $conn->fetchAssociative(
            'SELECT 
                COUNT(DISTINCT subscriber_id) as opened,
                COUNT(*) as total_opens
            FROM email_tracking 
            WHERE campaign_id = :campaign_id 
            AND event_type = :event_type',
            [
                'campaign_id' => $campaignId,
                'event_type' => 'open'
            ]
        );
        
        // Statistiques de clics
        $clickStats = $conn->fetchAssociative(
            'SELECT 
                COUNT(DISTINCT subscriber_id) as clicked,
                COUNT(*) as total_clicks
            FROM email_tracking 
            WHERE campaign_id = :campaign_id 
            AND event_type = :event_type',
            [
                'campaign_id' => $campaignId,
                'event_type' => 'click'
            ]
        );
        
        // Statistiques de désabonnements
        $unsubscribeStats = $conn->fetchAssociative(
            'SELECT COUNT(*) as unsubscribed
            FROM newsletter_subscriber 
            WHERE unsubscribed_at IS NOT NULL 
            AND id IN (
                SELECT subscriber_id FROM newsletter_campaign_subscriber 
                WHERE campaign_id = :campaign_id
            )',
            ['campaign_id' => $campaignId]
        );
        
        // Ouvertures par jour
        $dailyOpens = $conn->fetchAllAssociative(
            'SELECT 
                DATE(created_at) as date,
                COUNT(*) as opens,
                COUNT(DISTINCT subscriber_id) as unique_opens
            FROM email_tracking 
            WHERE campaign_id = :campaign_id 
            AND event_type = :event_type
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30',
            [
                'campaign_id' => $campaignId,
                'event_type' => 'open'
            ]
        );
        
        // Top 10 des liens cliqués
        $topLinks = $conn->fetchAllAssociative(
            'SELECT 
                link_url,
                COUNT(*) as clicks,
                COUNT(DISTINCT subscriber_id) as unique_clicks
            FROM email_tracking 
            WHERE campaign_id = :campaign_id 
            AND event_type = :event_type
            AND link_url IS NOT NULL
            GROUP BY link_url
            ORDER BY clicks DESC
            LIMIT 10',
            [
                'campaign_id' => $campaignId,
                'event_type' => 'click'
            ]
        );
        
        // Récupérer le nombre total d'envoyés
        $campaign = $this->getEntityManager()
            ->getRepository(NewsletterCampaign::class)
            ->find($campaignId);
        
        $totalSent = $campaign ? $campaign->getSentCount() : 0;
        
        $opened = (int)($openStats['opened'] ?? 0);
        $clicked = (int)($clickStats['clicked'] ?? 0);
        $unsubscribed = (int)($unsubscribeStats['unsubscribed'] ?? 0);
        
        return [
            'opened' => $opened,
            'clicked' => $clicked,
            'unsubscribed' => $unsubscribed,
            'bounced' => 0, // À implémenter si nécessaire
            'open_rate' => $totalSent > 0 ? round(($opened / $totalSent) * 100, 2) : 0,
            'click_rate' => $totalSent > 0 ? round(($clicked / $totalSent) * 100, 2) : 0,
            'daily_opens' => $dailyOpens,
            'top_links' => $topLinks,
        ];
    }
}
