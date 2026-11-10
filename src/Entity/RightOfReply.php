<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Droit de reponse d'une personne ou institution citee dans un contenu publie
 * (actualite, dossier, revendication, tribune). Rien n'est publie sans relecture humaine :
 * l'adresse e-mail reste privee, elle sert seulement a verifier l'identite.
 */
#[ORM\Entity]
#[ORM\Index(columns: ['target_type', 'target_id', 'status'])]
class RightOfReply
{
    public const TARGETS = ['actualite', 'dossier', 'revendication', 'tribune'];
    public const PENDING = 'pending';
    public const PUBLISHED = 'published';
    public const REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $targetType;

    #[ORM\Column]
    private int $targetId;

    /** Nom de la personne ou de l'institution qui repond (affiche). */
    #[ORM\Column(length: 120)]
    private string $authorName;

    /** Qualite ou fonction (facultatif, affichee). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $authorRole = null;

    /** Jamais affichee : sert a la moderation pour verifier l'identite. */
    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    #[ORM\Column(length: 12)]
    private string $status = self::PENDING;

    /** Vrai quand la moderation a verifie que l'auteur est bien la personne ou l'institution citee. */
    #[ORM\Column(options: ['default' => false])]
    private bool $identityChecked = false;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $moderationNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    public function __construct(string $targetType, int $targetId, string $authorName, string $email, string $body)
    {
        if (!in_array($targetType, self::TARGETS, true)) {
            throw new \InvalidArgumentException('Type de contenu inconnu.');
        }
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->authorName = mb_substr(trim($authorName), 0, 120);
        $this->email = mb_substr(trim($email), 0, 180);
        $this->body = trim($body);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTargetType(): string { return $this->targetType; }
    public function getTargetId(): int { return $this->targetId; }
    public function getAuthorName(): string { return $this->authorName; }
    public function getAuthorRole(): ?string { return $this->authorRole; }
    public function setAuthorRole(?string $r): static { $r = null === $r ? null : trim($r); $this->authorRole = '' === $r ? null : mb_substr((string) $r, 0, 120); return $this; }
    public function getEmail(): string { return $this->email; }
    public function getBody(): string { return $this->body; }
    public function getStatus(): string { return $this->status; }
    public function isPublished(): bool { return self::PUBLISHED === $this->status; }
    public function isIdentityChecked(): bool { return $this->identityChecked; }
    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }

    public function publish(bool $identityChecked): static
    {
        $this->status = self::PUBLISHED;
        $this->identityChecked = $identityChecked;
        $this->publishedAt = new \DateTimeImmutable();
        $this->moderationNote = null;

        return $this;
    }

    public function reject(string $note): static
    {
        $this->status = self::REJECTED;
        $this->moderationNote = mb_substr(trim($note), 0, 500);

        return $this;
    }

    public function reopen(): static
    {
        $this->status = self::PENDING;
        $this->publishedAt = null;

        return $this;
    }
}
