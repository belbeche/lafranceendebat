<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Message direct entre deux comptes : demandes des citoyens, contribuables et acteurs. Les échanges restent sur la plateforme. */
#[ORM\Entity]
#[ORM\Index(name: 'idx_dm_recipient_read', columns: ['recipient_id', 'read_at'])]
class DirectMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $sender;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(User $sender, User $recipient, string $body)
    {
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSender(): User { return $this->sender; }
    public function getRecipient(): User { return $this->recipient; }
    public function getBody(): string { return $this->body; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
}
