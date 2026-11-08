<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Scrutin consultatif : un avis du reseau, sans valeur electorale ni juridique. */
#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
#[ORM\Table(name: 'consultation')]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 160)]
    private string $question = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 3000)]
    private ?string $context = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $options = [];

    #[ORM\Column]
    private \DateTimeImmutable $opensAt;

    #[ORM\Column]
    private \DateTimeImmutable $closesAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->opensAt = new \DateTimeImmutable();
        $this->closesAt = new \DateTimeImmutable('+14 days');
    }

    public function getId(): ?int { return $this->id; }
    public function getQuestion(): string { return $this->question; }
    public function setQuestion(string $q): self { $this->question = trim($q); return $this; }
    public function getContext(): ?string { return $this->context; }
    public function setContext(?string $c): self { $this->context = $c !== null && trim($c) !== '' ? trim($c) : null; return $this; }
    /** @return list<string> */
    public function getOptions(): array { return $this->options; }
    /** @param list<string> $o */
    public function setOptions(array $o): self { $this->options = array_values($o); return $this; }
    public function getOpensAt(): \DateTimeImmutable { return $this->opensAt; }
    public function setOpensAt(\DateTimeImmutable $d): self { $this->opensAt = $d; return $this; }
    public function getClosesAt(): \DateTimeImmutable { return $this->closesAt; }
    public function setClosesAt(\DateTimeImmutable $d): self { $this->closesAt = $d; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isOpen(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->opensAt <= $now && $now < $this->closesAt;
    }

    public function isClosed(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable()) >= $this->closesAt;
    }
}
