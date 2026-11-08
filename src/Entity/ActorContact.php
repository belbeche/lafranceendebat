<?php

namespace App\Entity;

use App\Repository\ActorContactRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Contact professionnel d'un acteur public (mairie, elu, administration) importe pour un demarchage B2B.
 * Consentement d'abord : un SEUL message d'invitation est envoye (interet legitime, adresse professionnelle publiee, message en rapport
 * avec les fonctions, desinscription en un clic). Tout message suivant exige que la personne ait accepte via le lien d'acceptation.
 * Une personne desinscrite ou dont l'adresse a rebondi reste enregistree (liste de suppression) pour ne jamais etre recontactee.
 */
#[ORM\Entity(repositoryClass: ActorContactRepository::class)]
#[ORM\Table(name: 'actor_contact')]
#[ORM\UniqueConstraint(name: 'uniq_actor_contact_email', columns: ['email'])]
class ActorContact
{
    public const CATEGORIES = [
        'commune' => 'Commune ou intercommunalité',
        'departement' => 'Département',
        'region' => 'Région',
        'parlementaire' => 'Parlementaire',
        'administration' => 'Administration ou ministère',
        'association' => 'Association ou organisme public',
        'autre' => 'Autre acteur public',
    ];
    public const ACTIVE = 'actif';
    public const UNSUBSCRIBED = 'desinscrit';
    public const BOUNCED = 'rebond';
    public const COMPLAINT = 'plainte';

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $locale = null;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 160)]
    private string $organisation;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(length: 20)]
    private string $category = 'autre';

    /** Page publique ou l'adresse est publiee : preuve de l'origine des donnees (obligation d'information RGPD). */
    #[ORM\Column(length: 500)]
    private string $sourceUrl;

    #[ORM\Column(length: 20)]
    private string $status = self::ACTIVE;

    #[ORM\Column]
    private \DateTimeImmutable $importedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastContactedAt = null;

    /** Date de l'unique message d'invitation envoye (jamais de relance). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $invitedAt = null;

    /** Consentement explicite donne en cliquant le lien d'acceptation. Sans lui, aucun message autre que l'invitation. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consentAt = null;

    /** Preuve du consentement recueilli hors plateforme (date importée + mention de la preuve). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $consentProof = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $contactCount = 0;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    public function __construct(string $email, string $organisation, string $sourceUrl)
    {
        $this->email = mb_strtolower(trim($email));
        $this->organisation = mb_substr(trim($organisation), 0, 160);
        $this->sourceUrl = mb_substr(trim($sourceUrl), 0, 500);
        $this->importedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPhone(): ?string { return $this->phone; }
    public function getAddress(): ?string { return $this->address; }
    public function getPostalCode(): ?string { return $this->postalCode; }
    public function getCity(): ?string { return $this->city; }
    public function getWebsite(): ?string { return $this->website; }
    /** Coordonnees professionnelles publiees (aucune donnee personnelle) ; chaque champ est borne et nettoye. */
    public function setCoordinates(?string $phone, ?string $address, ?string $postalCode, ?string $city, ?string $website): static
    {
        $clean = static fn (?string $v, int $n): ?string => ($v = trim((string) $v)) !== '' ? mb_substr($v, 0, $n) : null;
        $this->phone = $clean($phone, 40);
        $this->address = $clean($address, 255);
        $this->postalCode = $clean($postalCode, 12);
        $this->city = $clean($city, 120);
        $site = $clean($website, 255);
        $this->website = $site && preg_match('#^https://#i', $site) ? $site : null;

        return $this;
    }
    public function getEmail(): string { return $this->email; }
    public function getOrganisation(): string { return $this->organisation; }
    public function getRole(): ?string { return $this->role; }
    public function setRole(?string $r): static { $this->role = $r !== null && trim($r) !== '' ? mb_substr(trim($r), 0, 160) : null; return $this; }
    public function getCategory(): string { return $this->category; }
    public function getCategoryLabel(): string { return self::CATEGORIES[$this->category] ?? $this->category; }
    public function setCategory(string $c): static { $this->category = isset(self::CATEGORIES[$c]) ? $c : 'autre'; return $this; }
    public function getSourceUrl(): string { return $this->sourceUrl; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
    public function getImportedAt(): \DateTimeImmutable { return $this->importedAt; }
    public function getLastContactedAt(): ?\DateTimeImmutable { return $this->lastContactedAt; }
    public function markContacted(): static { $this->lastContactedAt = new \DateTimeImmutable(); ++$this->contactCount; return $this; }
    /** Annule un marquage « contacté » quand l'envoi a échoué (le marquage précède l'envoi, pour ne jamais envoyer deux fois). */
    public function restoreContact(?\DateTimeImmutable $last, int $count, ?\DateTimeImmutable $invited): static { $this->lastContactedAt = $last; $this->contactCount = $count; $this->invitedAt = $invited; return $this; }
    public function getInvitedAt(): ?\DateTimeImmutable { return $this->invitedAt; }
    public function markInvited(): static { $this->invitedAt = new \DateTimeImmutable(); return $this; }
    public function getConsentAt(): ?\DateTimeImmutable { return $this->consentAt; }
    public function hasConsent(): bool { return $this->consentAt !== null && $this->status === self::ACTIVE; }
    public function getConsentProof(): ?string { return $this->consentProof; }
    public function recordConsent(\DateTimeImmutable $at, string $proof): static { $this->consentAt = $at; $this->consentProof = mb_substr($proof, 0, 255); return $this; }
    public function giveConsent(): static { $this->consentAt ??= new \DateTimeImmutable(); return $this; }
    public function getContactCount(): int { return $this->contactCount; }
    public function getUnsubscribedAt(): ?\DateTimeImmutable { return $this->unsubscribedAt; }
    public function unsubscribe(string $status = self::UNSUBSCRIBED): static { $this->status = $status; $this->unsubscribedAt ??= new \DateTimeImmutable(); return $this; }

    public function getLocale(): ?string { return $this->locale; }
    public function setLocale(?string $l): static { $this->locale = ($l !== null && in_array($l, \App\Entity\Revendication::LOCALES, true)) ? $l : null; return $this; }
}
