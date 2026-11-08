<?php

namespace App\Entity;

use App\Entity\ImportLog;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Repository\NewsletterSubscriberRepository;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Doctrine « NewsletterSubscriber ».
 *
 * Table : newsletter_subscriber. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(name: 'newsletter_subscriber')]
class NewsletterSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(name: 'subscribed_at')]
    private ?\DateTimeImmutable $subscribedAt = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $unsubscribeToken = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true, name: 'import_log_id')]
    private ?int $importLogId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $campaignName = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $campaignDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToMany(
        targetEntity: NewsletterCampaign::class,
        mappedBy: 'subscribers',
        cascade: ['persist', 'remove']
    )]
    private Collection $campaigns;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    /** Date de confirmation du double opt-in. Sans elle, l'abonne ne recoit aucune lettre. */
    #[ORM\Column(name: 'confirmed_at', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $bouncedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $markedAsSpamAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalOpens = 0;

    #[ORM\Column(nullable: true)]
    private ?int $totalClicks = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastOpenAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastClickAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;
    
    #[ORM\ManyToOne(targetEntity: ImportLog::class)]
    private ?ImportLog $importedFrom = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $locale = null;

    #[ORM\ManyToOne(
        targetEntity: ImportLog::class,
        inversedBy: 'newsletterSubscribers'
    )]
    #[ORM\JoinColumn(nullable: false)]
    private ?ImportLog $importLog = null;

    #[ORM\ManyToOne(targetEntity: ImportLog::class)]
    #[ORM\JoinColumn(nullable: true, name: 'import_id', referencedColumnName: 'id')]
    private ?ImportLog $import = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $displayName = null;

    /**
     * @var array|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tags = null;

    public function __construct()
    {
        $this->subscribedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
        $this->generateUnsubscribeToken();
        $this->campaigns = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getSubscribedAt(): ?\DateTimeImmutable
    {
        return $this->subscribedAt;
    }

    public function setSubscribedAt(\DateTimeImmutable $subscribedAt): static
    {
        $this->subscribedAt = $subscribedAt;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getUnsubscribeToken(): ?string
    {
        return $this->unsubscribeToken;
    }

    public function setUnsubscribeToken(?string $unsubscribeToken): static
    {
        $this->unsubscribeToken = $unsubscribeToken;
        return $this;
    }

    public function generateUnsubscribeToken(): void
    {
        $this->unsubscribeToken = bin2hex(random_bytes(32));
    }

    public function getImportLog(): ?ImportLog
    {
        return $this->importLog;
    }

    public function setImportLog(?ImportLog $importLog): self
    {
        $this->importLog = $importLog;
        return $this;
    }

    public function getImport(): ?ImportLog
    {
        return $this->import;
    }

    public function setImport(?ImportLog $import): self
    {
        $this->import = $import;
        return $this;
    }

    public function __toString(): string
    {
        return $this->email ?? '';
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
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

    public function getImportLogId(): ?int
    {
        return $this->importLogId;
    }

    public function setImportLogId(?int $importLogId): static
    {
        $this->importLogId = $importLogId;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getCampaignName(): ?string
    {
        return $this->campaignName;
    }

    public function setCampaignName(?string $campaignName): static
    {
        $this->campaignName = $campaignName;
        return $this;
    }

    public function getCampaignDate(): ?\DateTimeImmutable
    {
        return $this->campaignDate;
    }

    public function setCampaignDate(?\DateTimeImmutable $campaignDate): static
    {
        $this->campaignDate = $campaignDate;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function setCampaigns(Collection $campaigns): self
    {
        $this->campaigns = $campaigns;

        return $this;
    }

    public function getCampaigns(): Collection
    {
        return $this->campaigns;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    public function getUnsubscribedAt(): ?\DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function setUnsubscribedAt(?\DateTimeImmutable $unsubscribedAt): static
    {
        $this->unsubscribedAt = $unsubscribedAt;
        return $this;
    }

    public function getBouncedAt(): ?\DateTimeImmutable
    {
        return $this->bouncedAt;
    }

    public function setBouncedAt(?\DateTimeImmutable $bouncedAt): static
    {
        $this->bouncedAt = $bouncedAt;
        return $this;
    }

    public function getMarkedAsSpamAt(): ?\DateTimeImmutable
    {
        return $this->markedAsSpamAt;
    }

    public function setMarkedAsSpamAt(?\DateTimeImmutable $markedAsSpamAt): static
    {
        $this->markedAsSpamAt = $markedAsSpamAt;
        return $this;
    }

    public function getTotalOpens(): ?int
    {
        return $this->totalOpens;
    }

    public function setTotalOpens(?int $totalOpens): static
    {
        $this->totalOpens = $totalOpens;
        return $this;
    }

    public function incrementTotalOpens(): void
    {
        $this->totalOpens++;
        $this->lastOpenAt = new \DateTimeImmutable();
    }

    public function getTotalClicks(): ?int
    {
        return $this->totalClicks;
    }

    public function setTotalClicks(?int $totalClicks): static
    {
        $this->totalClicks = $totalClicks;
        return $this;
    }

    public function incrementTotalClicks(): void
    {
        $this->totalClicks++;
        $this->lastClickAt = new \DateTimeImmutable();
    }

    public function getLastOpenAt(): ?\DateTimeImmutable
    {
        return $this->lastOpenAt;
    }

    public function setLastOpenAt(?\DateTimeImmutable $lastOpenAt): static
    {
        $this->lastOpenAt = $lastOpenAt;
        return $this;
    }

    public function getLastClickAt(): ?\DateTimeImmutable
    {
        return $this->lastClickAt;
    }

    public function setLastClickAt(?\DateTimeImmutable $lastClickAt): static
    {
        $this->lastClickAt = $lastClickAt;
        return $this;
    }

    public function addCampaign(NewsletterCampaign $campaign): self
    {
        if (!$this->campaigns->contains($campaign)) {
            $this->campaigns->add($campaign);
            $campaign->addSubscriber($this); // Mettre à jour les deux côtés
        }

        return $this;
    }

    public function removeCampaign(NewsletterCampaign $campaign): self
    {
        if ($this->campaigns->removeElement($campaign)) {
            $campaign->removeSubscriber($this); // Mettre à jour les deux côtés
        }

        return $this;
    }

    /**
     * Get the value of phone
     */ 
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set the value of phone
     *
     * @return  self
     */ 
    public function setPhone($phone)
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * Get the value of status
     */ 
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the value of status
     *
     * @return  self
     */ 
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the value of importedFrom
     */ 
    public function getImportedFrom()
    {
        return $this->importedFrom;
    }

    /**
     * Set the value of importedFrom
     *
     * @return  self
     */ 
    public function setImportedFrom($importedFrom)
    {
        $this->importedFrom = $importedFrom;

        return $this;
    }

    /**
     * Get the value of locale
     */ 
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the value of locale
     *
     * @return  self
     */ 
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Get the value of tags
     */ 
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Set the value of tags
     *
     * @return  self
     */ 
    public function setTags($tags)
    {
        $this->tags = $tags;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Dans les lifecycle callbacks ou votre service, mettez à jour cette propriété
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;
        
        return $this;
    }
}