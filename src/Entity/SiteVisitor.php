<?php
// src/Entity/SiteVisitor.php

namespace App\Entity;

use App\Repository\SiteVisitorRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité Doctrine « SiteVisitor ».
 *
 * Table : nom par défaut de Doctrine. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: SiteVisitorRepository::class)]
class SiteVisitor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 45)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pageVisited = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $visitedAt = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    public function __construct()
    {
        $this->visitedAt = new \DateTimeImmutable();
    }

    // Getters et setters...
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getPageVisited(): ?string
    {
        return $this->pageVisited;
    }

    public function setPageVisited(?string $pageVisited): static
    {
        $this->pageVisited = $pageVisited;
        return $this;
    }

    public function getVisitedAt(): ?\DateTimeImmutable
    {
        return $this->visitedAt;
    }

    public function setVisitedAt(\DateTimeImmutable $visitedAt): static
    {
        $this->visitedAt = $visitedAt;
        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): static
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }
}