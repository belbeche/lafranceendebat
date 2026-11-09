<?php
// src/Entity/BlockedIp.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Doctrine "BlockedIp".
 *
 * Table : nom par défaut de Doctrine. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: 'App\Repository\BlockedIpRepository')]
#[ORM\Table(name: "blocked_ips")]
class BlockedIp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 45, unique: true)] // IPv6 peut faire jusqu'à 45 caractères
    private ?string $ipAddress = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: "datetime_immutable")]
    private ?\DateTimeImmutable $blockedAt = null;

    #[ORM\Column(type: "datetime_immutable", nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: "integer", options: ["default" => 0])]
    private int $attempts = 0;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $blockedBy = null;

    public function __construct()
    {
        $this->blockedAt = new \DateTimeImmutable();
    }

    // Getters et Setters
    public function getId(): ?int { return $this->id; }
    
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(string $ipAddress): self { $this->ipAddress = $ipAddress; return $this; }
    
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): self { $this->reason = $reason; return $this; }
    
    public function getBlockedAt(): ?\DateTimeImmutable { return $this->blockedAt; }
    public function setBlockedAt(\DateTimeImmutable $blockedAt): self { $this->blockedAt = $blockedAt; return $this; }
    
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self { $this->expiresAt = $expiresAt; return $this; }
    
    public function getAttempts(): int { return $this->attempts; }
    public function setAttempts(int $attempts): self { $this->attempts = $attempts; return $this; }
    public function incrementAttempts(): self { $this->attempts++; return $this; }
    
    public function getBlockedBy(): ?string { return $this->blockedBy; }
    public function setBlockedBy(?string $blockedBy): self { $this->blockedBy = $blockedBy; return $this; }
    
    public function isExpired(): bool 
    { 
        return $this->expiresAt && $this->expiresAt < new \DateTimeImmutable();
    }
    
    public function isActive(): bool
    {
        return !$this->isExpired();
    }
}