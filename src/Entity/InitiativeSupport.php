<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Soutien déclaré à l'initiative La France en Débat par un membre connecté (un seul par compte).
 * Rien n'est public sauf le total ; le prénom n'est affiché que si la personne l'a demandé (publicName renseigné).
 * Le soutien se retire à tout moment : la ligne est alors supprimée.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_support_user', columns: ['user_id'])]
class InitiativeSupport
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $publicName = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, ?string $publicName = null)
    {
        $this->user = $user;
        $name = null === $publicName ? '' : mb_substr(trim(strip_tags($publicName)), 0, 60);
        $this->publicName = '' === $name ? null : $name;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getPublicName(): ?string { return $this->publicName; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
