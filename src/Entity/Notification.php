<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Notification dans la plateforme (cloche) : le texte seul, jamais le contenu d'un message. */
#[ORM\Entity]
#[ORM\Index(name: 'idx_notif_user_read', columns: ['user_id', 'read_at'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 200)]
    private string $text;

    #[ORM\Column(length: 255)]
    private string $url;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function __construct(User $user, string $text, string $url)
    {
        $this->user = $user;
        $this->text = mb_substr($text, 0, 200);
        $this->url = mb_substr($url, 0, 255);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getText(): string { return $this->text; }
    public function getUrl(): string { return $this->url; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }
    public function isRead(): bool { return null !== $this->readAt; }
    public function markRead(): static { $this->readAt ??= new \DateTimeImmutable(); return $this; }
}
