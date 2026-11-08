<?php

namespace App\Entity;

use App\Repository\DiscussionReplyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Doctrine « DiscussionReply ».
 *
 * Table : nom par défaut de Doctrine. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: DiscussionReplyRepository::class)]
#[ORM\Index(name: 'idx_reply_discussion_status', columns: ['discussion_id', 'status'])]
class DiscussionReply
{
    use \App\Entity\Concern\Editable;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: null)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Discussion $discussion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Écrivez votre réponse.')]
    #[Assert\Length(min: 20, max: 6000, minMessage: 'Développez un peu : {{ limit }} caractères minimum.')]
    private ?string $body = null;

    /** "Ce que je comprends de votre position" : pratique de dialogue valorisee. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 800)]
    private ?string $reformulation = null;

    #[ORM\Column(length: 20)]
    private string $status = Proposition::STATUS_VALIDATED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $moderationNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getDiscussion(): ?Discussion { return $this->discussion; }
    public function setDiscussion(?Discussion $v): self { $this->discussion = $v; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $v): self { $this->author = $v; return $this; }
    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $v): self { $this->body = $v !== null ? trim($v) : null; return $this; }
    public function getReformulation(): ?string { return $this->reformulation; }
    public function setReformulation(?string $v): self { $v = $v !== null ? trim($v) : null; $this->reformulation = $v === '' ? null : $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function setModerationNote(?string $v): self { $this->moderationNote = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isVisible(): bool { return $this->status === Proposition::STATUS_VALIDATED; }
    public function getContent(): ?string { return $this->body; }
}
