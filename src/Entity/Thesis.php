<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Analyse sourcée d'un sujet public (budget, sources, évolution du texte, ce qu'il faut pour l'appliquer).
 * Le contenu est un JSON validé par ThesisWriter::validate() : chaque élément chiffré ou daté porte sa source.
 * Une analyse écrite par l'IA reste invisible tant qu'un modérateur ne l'a pas approuvée.
 */
#[ORM\Entity]
#[ORM\Table(name: 'thesis')]
class Thesis
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 120, unique: true)]
    private string $slug;
    #[ORM\Column(length: 200)]
    private string $title;
    /** Étiquette de rattachement : par exemple « lyceens » pour la mise en avant dans les revendications. */
    #[ORM\Column(length: 40)]
    private string $tag = '';
    #[ORM\Column(type: Types::TEXT)]
    private string $data = '{}';
    #[ORM\Column]
    private bool $aiGenerated = false;
    #[ORM\Column]
    private bool $approved = false;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $slug, string $title)
    {
        $this->slug = $slug;
        $this->title = mb_substr($title, 0, 200);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSlug(): string { return $this->slug; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = mb_substr($v, 0, 200); return $this; }
    public function getTag(): string { return $this->tag; }
    public function setTag(string $v): static { $this->tag = mb_substr($v, 0, 40); return $this; }
    public function getData(): array { return json_decode($this->data, true) ?: []; }
    public function setData(array $d, bool $ai): static { $this->data = (string) json_encode($d, JSON_UNESCAPED_UNICODE); $this->aiGenerated = $ai; $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function isAiGenerated(): bool { return $this->aiGenerated; }
    public function isApproved(): bool { return $this->approved; }
    public function setApproved(bool $v): static { $this->approved = $v; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
