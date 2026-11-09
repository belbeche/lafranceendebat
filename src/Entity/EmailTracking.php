<?php
// src/Entity/EmailTracking.php

namespace App\Entity;

use App\Repository\EmailTrackingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Doctrine "EmailTracking".
 *
 * Table : email_tracking. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: EmailTrackingRepository::class)]
#[ORM\Table(name: 'email_tracking')]
#[ORM\Index(name: 'idx_campaign_event', columns: ['campaign_id', 'event_type'])]
#[ORM\Index(name: 'idx_subscriber_event', columns: ['subscriber_id', 'event_type'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
class EmailTracking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'campaign_id')]
    private ?int $campaignId = null;

    #[ORM\Column(name: 'subscriber_id', nullable: true)]
    private ?int $subscriberId = null;

    #[ORM\Column(name: 'event_type', length: 20)]
    private ?string $eventType = null; // 'open', 'click', 'bounce', 'unsubscribe'

    #[ORM\Column(name: 'link_url', nullable: true, length: 2048)]
    private ?string $linkUrl = null;

    #[ORM\Column(name: 'ip_address', nullable: true, length: 45)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', nullable: true, length: 512)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'device_type', nullable: true, length: 20)]
    private ?string $deviceType = null;

    #[ORM\Column(name: 'created_at')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters et setters
    public function getId(): ?int { return $this->id; }
    public function getCampaignId(): ?int { return $this->campaignId; }
    public function setCampaignId(int $campaignId): self { $this->campaignId = $campaignId; return $this; }
    public function getSubscriberId(): ?int { return $this->subscriberId; }
    public function setSubscriberId(?int $subscriberId): self { $this->subscriberId = $subscriberId; return $this; }
    public function getEventType(): ?string { return $this->eventType; }
    public function setEventType(string $eventType): self { $this->eventType = $eventType; return $this; }
    public function getLinkUrl(): ?string { return $this->linkUrl; }
    public function setLinkUrl(?string $linkUrl): self { $this->linkUrl = $linkUrl; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): self { $this->ipAddress = $ipAddress; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): self { $this->userAgent = $userAgent; return $this; }
    public function getDeviceType(): ?string { return $this->deviceType; }
    public function setDeviceType(?string $deviceType): self { $this->deviceType = $deviceType; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
}