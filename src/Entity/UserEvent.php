<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Journal de securite : qui, quoi, quand, depuis quelle adresse IP et quel pays.
 * Conserve 12 mois puis supprime (app:journal:purger). Consultable par l'administration uniquement.
 * L'utilisateur est conserve meme apres suppression du compte (user_id vide, libelle masque) pour pouvoir signaler un abus.
 */
#[ORM\Entity]
#[ORM\Table(name: 'user_event')]
#[ORM\Index(columns: ['user_id', 'at'])]
#[ORM\Index(columns: ['type', 'at'])]
#[ORM\Index(columns: ['ip', 'at'])]
class UserEvent
{
    public const REGISTER = 'register';
    public const EMAIL_VERIFIED = 'email_verified';
    public const PAGE = 'page';
    public const LOGIN_OK = 'login_ok';
    public const LOGIN_FAIL = 'login_fail';
    public const LOGIN_SUSPECT = 'login_suspect';
    public const REGISTER_BLOCKED = 'register_blocked';
    public const IP_FLAGGED = 'ip_flagged';
    public const WARNING = 'warning';
    public const RESTRICTED = 'restricted';
    public const LIFTED = 'lifted';
    public const DEACTIVATED = 'deactivated';

    public const LABELS = [
        self::REGISTER => 'Nouvelle inscription',
        self::EMAIL_VERIFIED => 'Adresse e-mail confirmée',
        self::PAGE => 'Page visitée',
        self::LOGIN_OK => 'Connexion réussie',
        self::LOGIN_FAIL => 'Connexion échouée',
        self::LOGIN_SUSPECT => 'Tentatives de connexion répétées',
        self::REGISTER_BLOCKED => 'Inscription refusée (e-mail suspect)',
        self::IP_FLAGGED => 'Adresse IP signalée',
        self::WARNING => 'Avertissement envoyé',
        self::RESTRICTED => 'Compte restreint',
        self::LIFTED => 'Restriction levée',
        self::DEACTIVATED => 'Compte désactivé',
    ];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;
    #[ORM\Column(length: 190, nullable: true)]
    private ?string $label;
    #[ORM\Column(length: 24)]
    private string $type;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $detail;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $path;
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ip;
    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country;
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city;
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $device;
    #[ORM\Column]
    private \DateTimeImmutable $at;

    public function __construct(string $type, ?User $user, ?string $label, ?string $detail, ?string $path, ?string $ip, ?string $country, ?string $city, ?string $device)
    {
        $this->type = $type;
        $this->user = $user;
        $this->label = $label ? mb_substr($label, 0, 190) : null;
        $this->detail = $detail ? mb_substr($detail, 0, 255) : null;
        $this->path = $path ? mb_substr($path, 0, 255) : null;
        $this->ip = $ip ? mb_substr($ip, 0, 45) : null;
        $this->country = $country;
        $this->city = $city ? mb_substr($city, 0, 100) : null;
        $this->device = $device;
        $this->at = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function getLabel(): ?string { return $this->label; }
    public function getType(): string { return $this->type; }
    public function getTypeLabel(): string { return self::LABELS[$this->type] ?? $this->type; }
    public function getDetail(): ?string { return $this->detail; }
    public function getPath(): ?string { return $this->path; }
    public function getIp(): ?string { return $this->ip; }
    public function getCountry(): ?string { return $this->country; }
    public function getCity(): ?string { return $this->city; }
    public function getDevice(): ?string { return $this->device; }
    public function getAt(): \DateTimeImmutable { return $this->at; }
}
