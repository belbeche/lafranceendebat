<?php

namespace App\Entity;

use App\Repository\ImportLogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Doctrine "ImportLog".
 *
 * Table : import_log. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: ImportLogRepository::class)]
#[ORM\Table(name: 'import_log')]
#[ORM\HasLifecycleCallbacks]
class ImportLog
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_STOPPED = 'stopped';
    const STATUS_CANCELLED = 'cancelled';

    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_DEBUG = 'debug';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    private ?string $entityType = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $totalRecords = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $processedRecords = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $successfulRecords = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $failedRecords = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $skippedRecords = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $importedBy = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUpdate = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(
        targetEntity: NewsletterCampaign::class,
        inversedBy: 'importLogs'
    )]
    #[ORM\JoinColumn(nullable: true)]
    private ?NewsletterCampaign $campaign = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $totalLines = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $delimiter = ',';

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $hasHeader = true;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $skipDuplicates = true;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $level = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $lineNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rowData = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $errors = [];

    #[ORM\OneToMany(
        mappedBy: 'importLog',
        targetEntity: NewsletterSubscriber::class
    )]
    private Collection $newsletterSubscribers;

    private int $processedRows = 0;

    private int $skippedRows = 0;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $importName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $source = null;
    
    public function __construct()
    {
        $this->newsletterSubscribers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastUpdate = new \DateTimeImmutable();
        $this->config = [];
        $this->errors = [];
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastUpdate = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getTotalRecords(): ?int
    {
        return $this->totalRecords;
    }

    public function setTotalRecords(?int $totalRecords): self
    {
        $this->totalRecords = $totalRecords;
        return $this;
    }

    public function getProcessedRecords(): ?int
    {
        return $this->processedRecords ?? 0;
    }

    public function setProcessedRecords(?int $processedRecords): self
    {
        $this->processedRecords = $processedRecords;
        $this->lastUpdate = new \DateTimeImmutable();
        return $this;
    }

    public function getSuccessfulRecords(): ?int
    {
        return $this->successfulRecords ?? 0;
    }

    public function setSuccessfulRecords(?int $successfulRecords): self
    {
        $this->successfulRecords = $successfulRecords;
        $this->lastUpdate = new \DateTimeImmutable();
        return $this;
    }

    public function getFailedRecords(): ?int
    {
        return $this->failedRecords ?? 0;
    }

    public function setFailedRecords(?int $failedRecords): self
    {
        $this->failedRecords = $failedRecords;
        $this->lastUpdate = new \DateTimeImmutable();
        return $this;
    }

    public function getSkippedRecords(): ?int
    {
        return $this->skippedRecords ?? 0;
    }

    public function setSkippedRecords(?int $skippedRecords): self
    {
        $this->skippedRecords = $skippedRecords;
        $this->lastUpdate = new \DateTimeImmutable();
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeInterface $startedAt): self
    {
        if ($startedAt instanceof \DateTime) {
            $this->startedAt = \DateTimeImmutable::createFromMutable($startedAt);
        } else {
            $this->startedAt = $startedAt;
        }
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        if ($completedAt instanceof \DateTime) {
            $this->completedAt = \DateTimeImmutable::createFromMutable($completedAt);
        } else {
            $this->completedAt = $completedAt;
        }
        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;
        return $this;
    }

    public function getImportedBy(): ?User
    {
        return $this->importedBy;
    }

    public function setImportedBy(?User $importedBy): self
    {
        $this->importedBy = $importedBy;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config ?? [];
    }

    public function setConfig(?array $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getLastUpdate(): ?\DateTimeImmutable
    {
        return $this->lastUpdate;
    }

    public function setLastUpdate(?\DateTimeImmutable $lastUpdate): self
    {
        $this->lastUpdate = $lastUpdate;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCampaign(): ?NewsletterCampaign
    {
        return $this->campaign;
    }

    public function setCampaign(?NewsletterCampaign $campaign): self
    {
        $this->campaign = $campaign;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getTotalLines(): ?int
    {
        return $this->totalLines ?? $this->totalRecords;
    }

    public function setTotalLines(?int $totalLines): self
    {
        $this->totalLines = $totalLines;
        return $this;
    }

    public function getDelimiter(): ?string
    {
        return $this->delimiter;
    }

    public function setDelimiter(?string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    public function getHasHeader(): ?bool
    {
        return $this->hasHeader;
    }

    public function setHasHeader(?bool $hasHeader): self
    {
        $this->hasHeader = $hasHeader;
        return $this;
    }

    public function getSkipDuplicates(): ?bool
    {
        return $this->skipDuplicates;
    }

    public function setSkipDuplicates(?bool $skipDuplicates): self
    {
        $this->skipDuplicates = $skipDuplicates;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): self
    {
        $this->filePath = $filePath;
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

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    public function setLineNumber(?int $lineNumber): self
    {
        $this->lineNumber = $lineNumber;
        return $this;
    }

    public function getRowData(): ?string
    {
        return $this->rowData;
    }

    public function setRowData(?string $rowData): self
    {
        $this->rowData = $rowData;
        return $this;
    }

    public function getErrors(): array
    {
        return $this->errors ?? [];
    }

    public function setErrors(?array $errors): self
    {
        $this->errors = $errors;
        $this->lastUpdate = new \DateTimeImmutable();
        return $this;
    }

    public function addError(string $error): self
    {
        if (!in_array($error, $this->errors ?? [])) {
            $this->errors[] = $error;
        }
        return $this;
    }

    public function getNewsletterSubscribers(): Collection
    {
        return $this->newsletterSubscribers;
    }

    public function addNewsletterSubscriber(NewsletterSubscriber $newsletterSubscriber): self
    {
        if (!$this->newsletterSubscribers->contains($newsletterSubscriber)) {
            $this->newsletterSubscribers->add($newsletterSubscriber);
            $newsletterSubscriber->setImportLog($this);
        }
        return $this;
    }

    public function removeNewsletterSubscriber(NewsletterSubscriber $newsletterSubscriber): self
    {
        if ($this->newsletterSubscribers->removeElement($newsletterSubscriber)) {
            if ($newsletterSubscriber->getImportLog() === $this) {
                $newsletterSubscriber->setImportLog(null);
            }
        }
        return $this;
    }

    public function getName(): string
    {
        $date = $this->createdAt ? $this->createdAt->format('d/m/Y') : (new \DateTimeImmutable())->format('d/m/Y');
        return ($this->fileName ?? 'import') . ' (' . $date . ')';
    }

    public function getProgressPercentage(): float
    {
        if (!$this->totalRecords || $this->totalRecords === 0) {
            return 0;
        }

        $processed = $this->getProcessedRecords();
        return ($processed / $this->totalRecords) * 100;
    }

    public function getSuccessRate(): float
    {
        $processed = $this->getProcessedRecords();
        if ($processed === 0) {
            return 0;
        }

        $successful = $this->getSuccessfulRecords();
        return ($successful / $processed) * 100;
    }

    public function getFormattedStatus(): string
    {
        $statuses = [
            self::STATUS_PENDING => 'En attente',
            self::STATUS_PROCESSING => 'En cours',
            self::STATUS_COMPLETED => 'Terminé',
            self::STATUS_FAILED => 'Échoué',
            self::STATUS_STOPPED => 'Arrêté',
            self::STATUS_CANCELLED => 'Annulé'
        ];

        return $statuses[$this->status] ?? $this->status;
    }

    public function getFormattedDuration(): ?string
    {
        if (!$this->startedAt || !$this->completedAt) {
            return null;
        }

        $duration = $this->completedAt->getTimestamp() - $this->startedAt->getTimestamp();

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

    public function getStatistics(): array
    {
        return [
            'total' => $this->totalRecords ?? 0,
            'processed' => $this->getProcessedRecords(),
            'successful' => $this->getSuccessfulRecords(),
            'failed' => $this->getFailedRecords(),
            'skipped' => $this->getSkippedRecords(),
            'progress' => $this->getProgressPercentage(),
            'success_rate' => $this->getSuccessRate(),
            'status' => $this->getFormattedStatus(),
            'duration' => $this->getFormattedDuration()
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isStopped(): bool
    {
        return $this->status === self::STATUS_STOPPED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canStart(): bool
    {
        return $this->isPending() && $this->filePath !== null && file_exists($this->filePath);
    }

    public function canStop(): bool
    {
        return $this->isProcessing();
    }

    public function canCancel(): bool
    {
        return $this->isPending() || $this->isProcessing();
    }

    public function start(): self
    {
        $this->status = self::STATUS_PROCESSING;
        $this->startedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        
        if ($this->isFailed()) {
            $this->processedRecords = 0;
            $this->successfulRecords = 0;
            $this->failedRecords = 0;
            $this->skippedRecords = 0;
        }
        
        return $this;
    }

    public function complete(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function fail(?string $error = null): self
    {
        $this->status = self::STATUS_FAILED;
        if ($error !== null) {
            $this->error = $error;
            $this->errorMessage = $error;
        }
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function stop(): self
    {
        $this->status = self::STATUS_STOPPED;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function cancel(): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function incrementProcessed(int $amount = 1): self
    {
        $this->processedRecords = ($this->processedRecords ?? 0) + $amount;
        $this->lastUpdate = new \DateTimeImmutable();
        return $this;
    }

    public function incrementSuccessful(int $amount = 1): self
    {
        $this->successfulRecords = ($this->successfulRecords ?? 0) + $amount;
        $this->lastUpdate = new \DateTimeImmutable();
        return $this;
    }

    public function incrementFailed(int $amount = 1): self
    {
        $this->failedRecords = ($this->failedRecords ?? 0) + $amount;
        $this->lastUpdate = new \DateTimeImmutable();
        return $this;
    }

    public function incrementSkipped(int $amount = 1): self
    {
        $this->skippedRecords = ($this->skippedRecords ?? 0) + $amount;
        $this->lastUpdate = new \DateTimeImmutable();
        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->fileName;
    }

    public function setOriginalFilename(string $filename): self
    {
        $this->fileName = $filename;
        return $this;
    }

    public function getRecordsImported(): ?int
    {
        return $this->successfulRecords;
    }

    public function setRecordsImported(?int $recordsImported): self
    {
        $this->successfulRecords = $recordsImported;
        return $this;
    }

    public function getProcessedCount(): int
    {
        return $this->getProcessedRecords();
    }

    public function getImportedCount(): int
    {
        return $this->getSuccessfulRecords();
    }

    public function getFailedCount(): int
    {
        return $this->getFailedRecords();
    }

    public function getSkippedCount(): int
    {
        return $this->getSkippedRecords();
    }

    public function getTotalCount(): int
    {
        return $this->totalRecords ?? 0;
    }

    public function incrementProcessedRows(int $amount = 1): self
    {
        return $this->incrementProcessed($amount);
    }

    public function getSkippedRows(): int
    {
        return $this->skippedRows;
    }

    public function setSkippedRows(int $skippedRows): self
    {
        $this->skippedRows = $skippedRows;
        return $this;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function setProcessedRows(int $processedRows): self
    {
        $this->processedRows = $processedRows;
        return $this;
    }

    public function getImportName(): ?string
    {
        return $this->importName;
    }

    public function setImportName(?string $importName): self
    {
        $this->importName = $importName;
        
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function incrementSkippedRows(): self
    {
        $this->skippedRows = ($this->skippedRows ?? 0) + 1;
        return $this->incrementSkipped(1);
    }
}