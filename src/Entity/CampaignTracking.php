<?php

// src/Entity/CampaignTracking.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Doctrine "CampaignTracking".
 *
 * Table : campaign_tracking. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity]
#[ORM\Table(name: 'campaign_tracking')]
#[ORM\Index(name: 'idx_campaign_action', columns: ['campaign_id', 'action'])]
#[ORM\Index(name: 'idx_subscriber_action', columns: ['subscriber_id', 'action'])]
#[ORM\HasLifecycleCallbacks]
class CampaignTracking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: NewsletterCampaign::class, inversedBy: 'trackings')]
    #[ORM\JoinColumn(nullable: true)]
    private ?NewsletterCampaign $campaign = null;

    #[ORM\ManyToOne(targetEntity: NewsletterSubscriber::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?NewsletterSubscriber $subscriber = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null; // open, click, bounce, unsubscribe, spam

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null; // Pour les clics

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $deviceType = null; // desktop, mobile, tablet

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $bounceType = null; // hard, soft

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $bounceReason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampaign(): ?NewsletterCampaign
    {
        return $this->campaign;
    }

    public function setCampaign(?NewsletterCampaign $campaign): static
    {
        $this->campaign = $campaign;
        return $this;
    }

    public function getSubscriber(): ?NewsletterSubscriber
    {
        return $this->subscriber;
    }

    public function setSubscriber(?NewsletterSubscriber $subscriber): static
    {
        $this->subscriber = $subscriber;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function setDeviceType(?string $deviceType): static
    {
        $this->deviceType = $deviceType;
        return $this;
    }

    public function getBounceType(): ?string
    {
        return $this->bounceType;
    }

    public function setBounceType(?string $bounceType): static
    {
        $this->bounceType = $bounceType;
        return $this;
    }

    public function getBounceReason(): ?string
    {
        return $this->bounceReason;
    }

    public function setBounceReason(?string $bounceReason): static
    {
        $this->bounceReason = $bounceReason;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}