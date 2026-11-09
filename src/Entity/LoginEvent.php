<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Historique de connexion d'un compte : date, resultat, appareil et adresse IP raccourcie (RGPD). Conserve 90 jours. */
#[ORM\Entity]
#[ORM\Table(name: 'login_event')]
#[ORM\Index(columns: ['user_id', 'at'])]
class LoginEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;
    #[ORM\Column]
    private \DateTimeImmutable $at;
    #[ORM\Column]
    private bool $success;
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ip;
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $device;

    public function __construct(User $user, bool $success, ?string $ip, ?string $userAgent)
    {
        $this->user = $user;
        $this->success = $success;
        $this->at = new \DateTimeImmutable();
        $this->ip = self::shorten($ip);
        $this->device = self::describe($userAgent);
    }

    /** 192.168.4.27 devient 192.168.4.x ; IPv6 : 4 premiers blocs. */
    public static function shorten(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }
        if (str_contains($ip, ':')) {
            return implode(':', array_slice(explode(':', $ip), 0, 4)) . '::x';
        }

        return preg_replace('/\.\d+$/', '.x', $ip);
    }

    /** "Chrome sur Windows" : suffisant pour reconnaitre son appareil, sans empreinte precise. */
    public static function describe(?string $ua): ?string
    {
        if (!$ua) {
            return null;
        }
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Navigateur',
        };
        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'système inconnu',
        };

        return $browser . ' sur ' . $os;
    }

    public function getUser(): User { return $this->user; }
    public function getAt(): \DateTimeImmutable { return $this->at; }
    public function isSuccess(): bool { return $this->success; }
    public function getIp(): ?string { return $this->ip; }
    public function getDevice(): ?string { return $this->device; }
}
