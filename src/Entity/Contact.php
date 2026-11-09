<?php

// src/Entity/Contact.php

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Doctrine "Contact".
 *
 * Table : nom par défaut de Doctrine. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le prénom est obligatoire")]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Email(message: "L'email {{ value }} n'est pas valide")]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $telephone = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Veuillez sélectionner un sujet")]
    private ?string $sujet = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: "Le message est obligatoire")]
    #[Assert\Length(min: 10, max: 2000)]
    private ?string $message = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $traite = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->traite = false;
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getSujet(): ?string
    {
        return $this->sujet;
    }

    public function setSujet(string $sujet): static
    {
        $this->sujet = $sujet;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isTraite(): ?bool
    {
        return $this->traite;
    }

    public function setTraite(?bool $traite): static
    {
        $this->traite = $traite;

        return $this;
    }
}