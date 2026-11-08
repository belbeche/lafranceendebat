<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use App\Repository\NewsletterCampaignRepository;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Entité Doctrine « NewsletterCampaign ».
 *
 * Table : nom par défaut de Doctrine. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: NewsletterCampaignRepository::class)]
class NewsletterCampaign
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_SCHEDULED = 'scheduled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'pending'; // pending, processing, completed, failed

    #[ORM\Column]
    private ?int $totalRecipients = 0;

    #[ORM\Column]
    private ?int $sentCount = 0;

    #[ORM\Column]
    private ?int $failedCount = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $recipients = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUpdate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $template = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $previewText = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $senderName = null;

    #[ORM\Column(nullable: true)]
    private ?bool $trackOpens = true;

    #[ORM\Column(nullable: true)]
    private ?bool $trackClicks = true;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $stats = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $priority = 'normal'; // low, normal, high

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $preheader = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $unsubscribeToken = null;

    #[ORM\ManyToMany(
        targetEntity: NewsletterSubscriber::class,
        inversedBy: 'campaigns',
        cascade: ['persist']
    )]
    #[ORM\JoinTable(name: 'newsletter_campaign_subscriber')]
    private Collection $subscribers;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;
    
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $targetType = null;
    
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $targetId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $senderEmail = null;

    #[ORM\ManyToOne(targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $startedBy = null;

    #[ORM\OneToMany(
        mappedBy: 'campaign',
        targetEntity: ImportLog::class
    )]
    private Collection $importLogs;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $scheduledAt = null;

    #[ORM\OneToMany(
        mappedBy: 'campaign',
        targetEntity: CampaignTracking::class
    )]
    private Collection $trackings;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $uniqueOpens = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $uniqueClicks = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $totalOpens = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $totalClicks = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $hardBounces = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $softBounces = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $unsubscribes = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $complaints = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $linksTracking = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isAllSubscribers = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $scheduledFor = null;

    

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $this->lastUpdate = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $this->stats = [
            'opens' => 0,
            'clicks' => 0,
            'unique_clicks' => 0,
            'unique_opens' => 0,
            'unsubscribes' => 0,
            'bounces' => 0,
            'hard_bounces' => 0,
            'soft_bounces' => 0,
            'spam_reports' => 0
        ];
        $this->trackings = new ArrayCollection();
        $this->importLogs = new ArrayCollection(); // Initialiser ici
        $this->setUnsubscribeToken(bin2hex(random_bytes(16)));
        $this->subscribers = new ArrayCollection();
    }

    // Getters et Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    private function cleanHtmlContent(string $html): string
    {
        // Supprimer les balises dangereuses
        $dangerousTags = ['script', 'iframe', 'object', 'embed', 'applet'];
        foreach ($dangerousTags as $tag) {
            $html = preg_replace('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', '', $html);
        }
        
        // Nettoyer les attributs dangereux
        $html = preg_replace('/ on\w+="[^"]*"/', '', $html);
        $html = preg_replace('/ javascript:/', '', $html);
        
        return $html;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTotalRecipients(): ?int
    {
        return $this->totalRecipients;
    }

    public function setTotalRecipients(int $totalRecipients): static
    {
        $this->totalRecipients = $totalRecipients;
        return $this;
    }

    public function getSentCount(): ?int
    {
        return $this->sentCount;
    }

    public function setSentCount(int $sentCount): static
    {
        $this->sentCount = $sentCount;
        return $this;
    }

    public function getFailedCount(): ?int
    {
        return $this->failedCount;
    }

    public function setFailedCount(int $failedCount): static
    {
        $this->failedCount = $failedCount;
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

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): static
    {
        $this->error = $error;
        return $this;
    }

    public function getRecipients(): ?array
    {
        return $this->recipients;
    }

    public function setRecipients(?array $recipients): static
    {
        $this->recipients = $recipients;
        return $this;
    }

    public function getLastUpdate(): ?\DateTimeImmutable
    {
        return $this->lastUpdate;
    }

    public function setLastUpdate(?\DateTimeImmutable $lastUpdate): static
    {
        $this->lastUpdate = $lastUpdate;
        return $this;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): static
    {
        $this->template = $template;
        return $this;
    }

    public function getPreviewText(): ?string
    {
        return $this->previewText;
    }

    public function setPreviewText(?string $previewText): static
    {
        $this->previewText = $previewText;
        return $this;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function setSenderName(?string $senderName): static
    {
        $this->senderName = $senderName;
        return $this;
    }

    public function isTrackOpens(): ?bool
    {
        return $this->trackOpens;
    }

    public function setTrackOpens(?bool $trackOpens): static
    {
        $this->trackOpens = $trackOpens;
        return $this;
    }

    public function isTrackClicks(): ?bool
    {
        return $this->trackClicks;
    }

    public function setTrackClicks(?bool $trackClicks): static
    {
        $this->trackClicks = $trackClicks;
        return $this;
    }

    public function setStats(?array $stats): static
    {
        $this->stats = $stats;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(?string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    // Méthodes utilitaires

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getProgressPercentage(): float
    {
        if ($this->totalRecipients === 0) {
            return 0;
        }

        $processed = $this->sentCount + $this->failedCount;
        return min(100, ($processed / $this->totalRecipients) * 100);
    }

    public function getSuccessRate(): float
    {
        if ($this->sentCount === 0) {
            return 0;
        }

        $processed = $this->sentCount + $this->failedCount;
        if ($processed === 0) {
            return 0;
        }

        return ($this->sentCount / $processed) * 100;
    }

    public function incrementSentCount(int $amount = 1): void
    {
        $this->sentCount += $amount;
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function incrementFailedCount(int $amount = 1): void
    {
        $this->failedCount += $amount;
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function incrementOpenCount(): void
    {
        if (!isset($this->stats['opens'])) {
            $this->stats['opens'] = 0;
        }
        $this->stats['opens']++;
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function incrementClickCount(): void
    {
        if (!isset($this->stats['clicks'])) {
            $this->stats['clicks'] = 0;
        }
        $this->stats['clicks']++;
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function incrementUnsubscribeCount(): void
    {
        if (!isset($this->stats['unsubscribes'])) {
            $this->stats['unsubscribes'] = 0;
        }
        $this->stats['unsubscribes']++;
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function incrementBounceCount(): void
    {
        if (!isset($this->stats['bounces'])) {
            $this->stats['bounces'] = 0;
        }
        $this->stats['bounces']++;
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function getOpenRate(): float
    {
        if ($this->sentCount === 0) {
            return 0;
        }

        $opens = $this->stats['opens'] ?? 0;
        return ($opens / $this->sentCount) * 100;
    }

    public function getClickRate(): float
    {
        if ($this->sentCount === 0) {
            return 0;
        }

        $clicks = $this->stats['clicks'] ?? 0;
        return ($clicks / $this->sentCount) * 100;
    }

    public function getDuration(): ?int
    {
        if (!$this->startedAt || !$this->completedAt) {
            return null;
        }

        return $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();
    }

    public function getFormattedDuration(): ?string
    {
        $duration = $this->getDuration();
        
        if ($duration === null) {
            return null;
        }

        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        $seconds = $duration % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm %02ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }

    public function getPreheader(): ?string
    {
        return $this->preheader;
    }

    public function setPreheader(?string $preheader): self
    {
        $this->preheader = $preheader;

        return $this;
    }

    public function getUnsubscribeToken(): ?string
    {
        return $this->unsubscribeToken;
    }

    public function setUnsubscribeToken(?string $unsubscribeToken): self
    {
        $this->unsubscribeToken = $unsubscribeToken;
        return $this;
    }

    public function setCreatedBy(?User $user): self
    {
        $this->createdBy = $user;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setTargetType(string $targetType): self
    {
        $this->targetType = $targetType;
        return $this;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function setTargetId(?int $targetId): self
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function getTargetId(): ?int
    {
        return $this->targetId;
    }

    public function trackOpen(?NewsletterSubscriber $subscriber = null): void
    {
        $this->stats['opens']++;
        
        if ($subscriber && !$this->hasOpened($subscriber)) {
            $this->stats['unique_opens']++;
        }
        
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function trackClick(?NewsletterSubscriber $subscriber = null, ?string $url = null): void
    {
        $this->stats['clicks']++;
        
        if ($subscriber && !$this->hasClicked($subscriber)) {
            $this->stats['unique_clicks']++;
        }
        
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function trackBounce(?NewsletterSubscriber $subscriber = null, string $type = 'hard'): void
    {
        $this->stats['bounces']++;
        
        if ($type === 'hard') {
            $this->stats['hard_bounces']++;
            // Désactiver l'abonné pour les hard bounces
            if ($subscriber) {
                $subscriber->setIsActive(false);
                $subscriber->setBouncedAt(new \DateTimeImmutable());
            }
        } else {
            $this->stats['soft_bounces']++;
        }
        
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function trackUnsubscribe(NewsletterSubscriber $subscriber): void
    {
        $this->stats['unsubscribes']++;
        $subscriber->setIsActive(false);
        $subscriber->setUnsubscribedAt(new \DateTimeImmutable());
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function trackSpamReport(NewsletterSubscriber $subscriber): void
    {
        $this->stats['spam_reports']++;
        $subscriber->setIsActive(false);
        $subscriber->setMarkedAsSpamAt(new \DateTimeImmutable());
        $this->lastUpdate = new \DateTimeImmutable();
    }

    private function hasOpened(NewsletterSubscriber $subscriber): bool
    {
        // Vérifier dans les trackings si l'utilisateur a déjà ouvert
        foreach ($this->trackings as $tracking) {
            if ($tracking->getSubscriber() === $subscriber && $tracking->getAction() === 'open') {
                return true;
            }
        }
        return false;
    }

    private function hasClicked(NewsletterSubscriber $subscriber): bool
    {
        // Vérifier dans les trackings si l'utilisateur a déjà cliqué
        foreach ($this->trackings as $tracking) {
            if ($tracking->getSubscriber() === $subscriber && $tracking->getAction() === 'click') {
                return true;
            }
        }
        return false;
    }

    // Getters et Setters pour trackings
    public function getTrackings(): Collection
    {
        return $this->trackings;
    }

    public function addTracking(CampaignTracking $tracking): static
    {
        if (!$this->trackings->contains($tracking)) {
            $this->trackings->add($tracking);
            $tracking->setCampaign($this);
        }
        return $this;
    }

    public function removeTracking(CampaignTracking $tracking): static
    {
        if ($this->trackings->removeElement($tracking)) {
            if ($tracking->getCampaign() === $this) {
                $tracking->setCampaign(null);
            }
        }
        return $this;
    }

     public function shouldStartNow(): bool
    {
        // Si aucune date planifiée, démarrer immédiatement
        if ($this->scheduledFor === null) {
            return true;
        }
        
        // Vérifier si l'heure planifiée est passée
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        return $this->scheduledFor <= $now;
    }
    
    public function getTimeUntilStart(): ?string
    {
        if ($this->scheduledFor === null) {
            return null;
        }
        
        $now = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        
        if ($this->scheduledFor <= $now) {
            return 'Démarrer maintenant';
        }
        
        $interval = $now->diff($this->scheduledFor);
        
        if ($interval->d > 0) {
            return sprintf('Dans %d jour(s)', $interval->d);
        } elseif ($interval->h > 0) {
            return sprintf('Dans %d heure(s)', $interval->h);
        } else {
            return sprintf('Dans %d minute(s)', $interval->i);
        }
    }
    
    public function isScheduled(): bool
    {
        return $this->scheduledFor !== null && $this->scheduledFor > new \DateTime('now', new \DateTimeZone('Europe/Paris'));
    }

    /**
     * Get the value of email
     */ 
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set the value of email
     *
     * @return  self
     */ 
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    public function getSenderEmail(): ?string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(?string $senderEmail): static
    {
        $this->senderEmail = $senderEmail;
        return $this;
    }

    
    public function getSubscribers(): Collection
    {
        return $this->subscribers;
    }

    public function setSubscribers(Collection $subscribers): self
    {
        $this->subscribers = $subscribers;

        return $this;
    }

    public function addSubscriber(NewsletterSubscriber $subscriber): self
    {
        if (!$this->subscribers->contains($subscriber)) {
            $this->subscribers[] = $subscriber;
            $subscriber->addCampaign($this); // Important : mettre à jour les deux côtés
        }

        return $this;
    }

    public function removeSubscriber(NewsletterSubscriber $subscriber): self
    {
        if ($this->subscribers->removeElement($subscriber)) {
            $subscriber->removeCampaign($this); // Important : mettre à jour les deux côtés
        }

        return $this;
    }

    /**
     * Vide tous les abonnés
     */
    public function clearSubscribers(): self
    {
        foreach ($this->subscribers as $subscriber) {
            $subscriber->removeCampaign($this);
        }
        $this->subscribers->clear();

        return $this;
    }

    public function setContent(string $content): static
    {
        // Nettoyer le contenu HTML
        $cleanContent = $this->cleanHtmlContent($content);
        
        // Validation minimale pour les templates HTML
        if (empty(trim($cleanContent))) {
            throw new \InvalidArgumentException('Le contenu ne peut pas être vide.');
        }
        
        // Pour les templates HTML, vérifier la structure
        $stripped = strip_tags($cleanContent);
        $stripped = preg_replace('/\s+/', '', $stripped);
        
        if (empty($stripped)) {
            // Vérifier si c'est un template HTML structuré
            $hasHtmlStructure = preg_match('/<(html|body|div|section|table|main|article|header|footer)[\s>]/i', $cleanContent);
            if (!$hasHtmlStructure) {
                throw new \InvalidArgumentException('Le contenu HTML n\'est pas valide.');
            }
        }
        
        $this->content = $cleanContent;
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeInterface $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    /**
     * Get the value of importLogs
     */ 
    public function getImportLogs()
    {
        return $this->importLogs;
    }

    /**
     * Set the value of importLogs
     *
     * @return  self
     */ 
    public function setImportLogs($importLogs)
    {
        $this->importLogs = $importLogs;

        return $this;
    }

    public function setStartedBy(?User $user): self
    {
        $this->startedBy = $user;
        return $this;
    }

    public function getStartedBy(): ?User
    {
        return $this->startedBy;
    }

    public function getUniqueOpenRate(): float
    {
        if (!$this->totalRecipients || $this->totalRecipients === 0) {
            return 0;
        }
        return round(($this->uniqueOpens / $this->totalRecipients) * 100, 2);
    }

    public function getUniqueClickRate(): float
    {
        if (!$this->uniqueOpens || $this->uniqueOpens === 0) {
            return 0;
        }
        return round(($this->uniqueClicks / $this->uniqueOpens) * 100, 2);
    }

    public function getBounceRate(): float
    {
        $totalBounces = ($this->hardBounces + $this->softBounces);
        if (!$this->totalRecipients || $this->totalRecipients === 0) {
            return 0;
        }
        return round(($totalBounces / $this->totalRecipients) * 100, 2);
    }

    public function getLinksTracking(): ?array
    {
        return $this->linksTracking;
    }

    public function setLinksTracking(?array $linksTracking): self
    {
        $this->linksTracking = $linksTracking;

        return $this;
    }

    public function getStats(): array
    {
        $total = $this->getTotalRecipients() ?: 0;
        $sent = $this->getSentCount() ?: 0;
        $failed = $this->getFailedCount() ?: 0;
        $processed = $sent + $failed;
        $progress = $total > 0 ? round(($processed / $total) * 100) : 0;
        
        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'processed' => $processed,
            'progress' => $progress,
            'success_rate' => $processed > 0 ? round(($sent / $processed) * 100, 2) : 0,
            'status' => $this->getStatus(),
        ];
    }

    public function isAllSubscribers(): bool
    {
        return $this->isAllSubscribers ?? false;
    }

    public function setIsAllSubscribers(bool $isAllSubscribers): self
    {
        $this->isAllSubscribers = $isAllSubscribers;
        return $this;
    }

    /**
     * Obtient le temps écoulé depuis le début
     */
    public function getElapsedTime(): ?string
    {
        if (!$this->getStartedAt()) {
            return null;
        }
        
        $now = new \DateTimeImmutable();
        $startedAt = $this->getStartedAt();
        $interval = $now->diff($startedAt);
        
        if ($interval->d > 0) {
            return $interval->format('%d jours %h heures %i minutes');
        } elseif ($interval->h > 0) {
            return $interval->format('%h heures %i minutes');
        } else {
            return $interval->format('%i minutes');
        }
    }

    /**
     * Get the value of unsubscribes
     */ 
    public function getUnsubscribes()
    {
        return $this->unsubscribes;
    }

    /**
     * Set the value of unsubscribes
     *
     * @return  self
     */ 
    public function setUnsubscribes($unsubscribes)
    {
        $this->unsubscribes = $unsubscribes;

        return $this;
    }

    /**
     * Get the value of totalOpens
     */ 
    public function getTotalOpens()
    {
        return $this->totalOpens;
    }

    /**
     * Set the value of totalOpens
     *
     * @return  self
     */ 
    public function setTotalOpens($totalOpens)
    {
        $this->totalOpens = $totalOpens;

        return $this;
    }

    /**
     * Get the value of totalClicks
     */ 
    public function getTotalClicks()
    {
        return $this->totalClicks;
    }

    /**
     * Set the value of totalClicks
     *
     * @return  self
     */ 
    public function setTotalClicks($totalClicks)
    {
        $this->totalClicks = $totalClicks;

        return $this;
    }

    public function getScheduledFor(): ?\DateTimeInterface
    {
        return $this->scheduledFor;
    }

    public function setScheduledFor(?\DateTimeInterface $scheduledFor): self
    {
        $this->scheduledFor = $scheduledFor;
        return $this;
    }

    /**
     * Get the value of complaints
     */ 
    public function getComplaints()
    {
        return $this->complaints;
    }

    /**
     * Set the value of complaints
     *
     * @return  self
     */ 
    public function setComplaints($complaints)
    {
        $this->complaints = $complaints;

        return $this;
    }
}