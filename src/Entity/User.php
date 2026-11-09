<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Doctrine "User".
 *
 * Table : users. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', columns: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé par un autre utilisateur.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Comment::class)]
    private Collection $comments;

    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Post::class)]
    private Collection $posts;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $stripeAccountId = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $stripeReady = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    // Minimisation des donnees (RGPD) : l'adresse complete n'est plus exigee.
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $avatarPath = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    /** Participation volontaire au classement public (desactivee par defaut). */
    #[ORM\Column(options: ['default' => false])]
    private bool $rankingOptIn = false;

    /** E-mails sur l'activite liee a mes propres contributions (reponses, decisions). Actifs par defaut, desactivables. */
    #[ORM\Column(options: ['default' => true])]
    private bool $notifyOwnActivity = true;

    /** E-mail a chaque nouveau debat publie : sur consentement uniquement (desactive par defaut). */
    #[ORM\Column(options: ['default' => false])]
    private bool $notifyNewDebates = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isEnabled = true;

    #[ORM\ManyToOne(targetEntity: ImportLog::class)]
    private ?ImportLog $importLog = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    /** Date d'envoi de l'avis d'inactivite (conservation limitee). Vide si aucun avis en cours. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $inactivityNoticeAt = null;

    /** Toute session ouverte avant cette date est fermee ("deconnecter les autres appareils"). */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sessionsRevokedAt = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $status = null;

    /** Date d'acceptation de la charte de paix (preuve que le participant s'est engage au dialogue respectueux). */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $charterAcceptedAt = null;

    private ?string $plainPassword = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->comments = new ArrayCollection();
        $this->posts = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email ?? null; }
    public function setEmail(string $email): self { $this->email = mb_strtolower(trim($email)); return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function eraseCredentials(): void { $this->plainPassword = null; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $firstName): self { $this->firstName = $firstName; return $this; }
    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $lastName): self { $this->lastName = $lastName; return $this; }

    public function getFullName(): string { return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? '')); }

    /** Nom affiche publiquement : prenom et initiale du nom, pour limiter l'exposition des personnes. */
    public function getPublicName(): string
    {
        $first = trim((string) $this->firstName);
        $initial = $this->lastName ? mb_strtoupper(mb_substr(trim($this->lastName), 0, 1)) . '.' : '';
        $name = trim($first . ' ' . $initial);

        return $name !== '' ? $name : 'Citoyen';
    }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    public function getPostalCode(): ?string { return $this->postalCode; }
    public function setPostalCode(?string $postalCode): self { $this->postalCode = $postalCode; return $this; }
    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $city): self { $this->city = $city; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): self { $this->isVerified = $isVerified; return $this; }

    public function getAvatarPath(): ?string { return $this->avatarPath; }
    public function setAvatarPath(?string $avatarPath): self { $this->avatarPath = $avatarPath; return $this; }

    public function getToken(): ?string { return $this->token; }
    public function setToken(?string $token): self { $this->token = $token; return $this; }

    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    public function isNotifyOwnActivity(): bool { return $this->notifyOwnActivity; }
    public function setNotifyOwnActivity(bool $v): self { $this->notifyOwnActivity = $v; return $this; }
    public function isNotifyNewDebates(): bool { return $this->notifyNewDebates; }
    public function setNotifyNewDebates(bool $v): self { $this->notifyNewDebates = $v; return $this; }
    public function isRankingOptIn(): bool { return $this->rankingOptIn; }
    public function setRankingOptIn(bool $v): self { $this->rankingOptIn = $v; return $this; }
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $restrictedAt = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $restrictionReason = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $warnedAt = null;

    /** Compte restreint : lecture et participation normales, mais aucun paiement ni ouverture de compte Stripe. */
    public function isRestricted(): bool { return null !== $this->restrictedAt; }
    public function getRestrictedAt(): ?\DateTimeImmutable { return $this->restrictedAt; }
    public function getRestrictionReason(): ?string { return $this->restrictionReason; }
    public function restrict(string $reason): static { $this->restrictedAt ??= new \DateTimeImmutable(); $this->restrictionReason = mb_substr($reason, 0, 255); return $this; }
    public function liftRestriction(): static { $this->restrictedAt = null; $this->restrictionReason = null; return $this; }
    /** Comptes crees a partir de cette date : l'adresse e-mail doit etre confirmee avant tout paiement (les comptes plus anciens ne sont pas bloques). */
    public const EMAIL_VERIFICATION_SINCE = '2026-11-09';
    public function needsEmailVerification(): bool { return !$this->isVerified && $this->createdAt >= new \DateTimeImmutable(self::EMAIL_VERIFICATION_SINCE); }
    /** Aucun paiement ni compte Stripe : compte restreint, ou adresse e-mail non confirmee (comptes recents). */
    public function isPaymentBlocked(): bool { return $this->isRestricted() || $this->needsEmailVerification(); }
    public function getWarnedAt(): ?\DateTimeImmutable { return $this->warnedAt; }
    public function markWarned(): static { $this->warnedAt = new \DateTimeImmutable(); return $this; }

    public function getIsActive(): bool { return $this->isActive; }
    public function isActive(): bool { return $this->isActive; }

    public function setIsEnabled(bool $isEnabled): self { $this->isEnabled = $isEnabled; return $this; }
    public function isEnabled(): ?bool { return $this->isEnabled; }

    public function getImportLog(): ?ImportLog { return $this->importLog; }
    public function setImportLog(?ImportLog $importLog): self { $this->importLog = $importLog; return $this; }

    public function getSessionsRevokedAt(): ?\DateTimeImmutable { return $this->sessionsRevokedAt; }
    public function revokeSessions(): static { $this->sessionsRevokedAt = new \DateTimeImmutable(); return $this; }
    /** Valeur scalaire incluse dans la signature "rester connecte" : la revocation invalide aussi ces cookies. */
    public function getSessionStamp(): string { return $this->sessionsRevokedAt ? (string) $this->sessionsRevokedAt->getTimestamp() : '0'; }
    public function getInactivityNoticeAt(): ?\DateTimeImmutable { return $this->inactivityNoticeAt; }
    public function setInactivityNoticeAt(?\DateTimeImmutable $v): self { $this->inactivityNoticeAt = $v; return $this; }
    public function getLastLogin() { return $this->lastLogin; }
    public function setLastLogin($lastLogin) { $this->lastLogin = $lastLogin; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): self { $this->country = $country; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $status): self { $this->status = $status; return $this; }

    public function getCharterAcceptedAt(): ?\DateTimeImmutable { return $this->charterAcceptedAt; }
    public function setCharterAcceptedAt(?\DateTimeImmutable $at): self { $this->charterAcceptedAt = $at; return $this; }
    public function hasAcceptedCharter(): bool { return $this->charterAcceptedAt !== null; }

    public function getPlainPassword(): ?string { return $this->plainPassword; }
    public function setPlainPassword(string $plainPassword): void { $this->plainPassword = $plainPassword; }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection { return $this->comments; }
    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setAuthor($this);
        }

        return $this;
    }
    public function removeComment(Comment $comment): self
    {
        if ($this->comments->removeElement($comment) && $comment->getAuthor() === $this) {
            $comment->setAuthor(null);
        }

        return $this;
    }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection { return $this->posts; }
    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setAuthor($this);
        }

        return $this;
    }
    public function removePost(Post $post): self
    {
        if ($this->posts->removeElement($post) && $post->getAuthor() === $this) {
            $post->setAuthor(null);
        }

        return $this;
    }

    public function __toString(): string { return $this->getFullName() ?: (string) $this->email; }

    public function getStripeAccountId(): ?string { return $this->stripeAccountId; }
    public function setStripeAccountId(?string $id): self { $this->stripeAccountId = $id; return $this; }
    public function isStripeReady(): bool { return $this->stripeReady; }
    public function setStripeReady(bool $ready): self { $this->stripeReady = $ready; return $this; }
}
