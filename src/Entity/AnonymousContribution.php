<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Contribution déposée sans compte, notamment par un acteur public qui préfère ne pas s'exposer.
 *
 * Confidentialité : on ne conserve ni adresse IP, ni e-mail, ni identifiant de navigateur.
 * Le champ "contact" est facultatif et ne sert qu'à répondre si la personne le demande elle-même.
 * Rien n'est publié avant relecture par un modérateur (statut "nouveau" puis "publiee" ou "refusee").
 */
#[ORM\Entity]
#[ORM\Table(name: 'anonymous_contribution')]
class AnonymousContribution
{
    public const NEW = 'nouveau';
    public const PUBLISHED = 'publiee';
    public const REFUSED = 'refusee';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Clé de ActorContact::CATEGORIES (ou "citoyen"). */
    #[ORM\Column(length: 30)]
    private string $category = 'citoyen';

    #[ORM\Column(length: 160)]
    private string $subject = '';

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $contact = null;

    #[ORM\Column(length: 12)]
    private string $status = self::NEW;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $c): static { $this->category = ('citoyen' === $c || isset(ActorContact::CATEGORIES[$c])) ? $c : 'citoyen'; return $this; }
    public function getSubject(): string { return $this->subject; }
    public function setSubject(string $s): static { $this->subject = mb_substr(trim($s), 0, 160); return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $m): static { $this->message = mb_substr(trim($m), 0, 4000); return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $u): static { $u = trim((string) $u); $this->sourceUrl = ('' !== $u && preg_match('~^https?://~i', $u)) ? mb_substr($u, 0, 160) : null; return $this; }
    public function getContact(): ?string { return $this->contact; }
    public function setContact(?string $c): static { $c = trim((string) $c); $this->contact = '' === $c ? null : mb_substr($c, 0, 160); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = in_array($s, [self::NEW, self::PUBLISHED, self::REFUSED], true) ? $s : $this->status; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
