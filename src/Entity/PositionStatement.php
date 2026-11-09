<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Affirmation de la page "Où se situent-ils ?" (ex. : "Il faut réduire l'immigration légale"). Formulée de façon la plus factuelle possible. */
#[ORM\Entity]
class PositionStatement
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 80)]
    private string $theme;
    #[ORM\Column(length: 300)]
    private string $text;
    #[ORM\Column]
    private bool $published = false;
    /** Synthèse neutre en quelques lignes, rédigée par l'éditeur. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $synthesis = null;
    /**
     * Ce qu'on sait de la faisabilité, par axe (voir VIABILITY_AXES). Chaque axe : text + source_url.
     * Pas de note globale : un axe sans source est affiché "non documenté", jamais deviné.
     *
     * @var array<string,array{text:string,source_url:string}>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $viability = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Pour ajouter un axe, l'ajouter ici : le formulaire d'administration et la page publique le reprennent. */
    public const VIABILITY_AXES = ['cout' => 'Coût et financement', 'droit' => 'Cadre juridique (Constitution, droit européen)', 'mise_en_oeuvre' => 'Mise en œuvre (délai, moyens)'];

    public function __construct(string $theme, string $text)
    {
        $this->theme = mb_substr(trim(strip_tags($theme)), 0, 80);
        $this->text = mb_substr(trim(strip_tags($text)), 0, 300);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTheme(): string { return $this->theme; }
    public function getText(): string { return $this->text; }
    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $v): static { $this->published = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getSynthesis(): ?string { return $this->synthesis; }
    public function setSynthesis(?string $v): static { $v = null === $v ? null : trim(strip_tags($v)); $this->synthesis = '' === $v ? null : mb_substr((string) $v, 0, 1500); return $this; }
    /** @return array<string,array{text:string,source_url:string}> */
    public function getViability(): array { return $this->viability ?? []; }
    /** Un axe n'est gardé que s'il a un texte ; une adresse qui n'est pas en https est écartée. */
    public function setViabilityAxis(string $axis, string $text, string $url): static
    {
        if (!isset(self::VIABILITY_AXES[$axis])) {
            return $this;
        }
        $text = mb_substr(trim(strip_tags($text)), 0, 500);
        $url = str_starts_with(trim($url), 'https://') ? mb_substr(trim($url), 0, 400) : '';
        $v = $this->viability ?? [];
        if ('' === $text) {
            unset($v[$axis]);
        } else {
            $v[$axis] = ['text' => $text, 'source_url' => $url];
        }
        $this->viability = $v ?: null;

        return $this;
    }
}
