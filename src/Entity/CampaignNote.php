<?php

// src/Entity/CampaignNote.php

namespace App\Entity;

use App\Repository\CampaignNoteRepository;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Doctrine « CampaignNote ».
 *
 * Table : nom par défaut de Doctrine. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: CampaignNoteRepository::class)]
#[ORM\Table(name: "campaign_notes")]
class CampaignNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "integer")]
    private ?int $campaignId = null;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $campaignTitle = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: "text")]
    private ?string $content = null;

    #[ORM\Column(type: "string", length: 100)]
    private ?string $author = null;

    #[ORM\Column(type: "datetime_immutable")]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ID
    public function getId(): ?int
    {
        return $this->id;
    }

    // CampaignId
    public function getCampaignId(): ?int
    {
        return $this->campaignId;
    }

    public function setCampaignId(int $campaignId): self
    {
        $this->campaignId = $campaignId;
        return $this;
    }

    // CampaignTitle
    public function getCampaignTitle(): ?string
    {
        return $this->campaignTitle;
    }

    public function setCampaignTitle(?string $campaignTitle): self
    {
        $this->campaignTitle = $campaignTitle;
        return $this;
    }

    // Status
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    // Content
    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    // Author
    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;
        return $this;
    }

    // CreatedAt
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    // UpdatedAt
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}