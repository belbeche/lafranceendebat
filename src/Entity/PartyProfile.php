<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Fiche d'une formation politique (ou d'un groupe parlementaire) : liens OFFICIELS qu'elle publie elle-meme.
 * Une fiche est reperee par le sigle de groupe de ses propositions (PartyProposal::groupCode).
 * Aucun jugement : la fiche ne contient que des adresses https fournies par la formation, sans note ni classement.
 */
#[ORM\Entity]
#[ORM\Table(name: 'party_profile')]
#[ORM\UniqueConstraint(name: 'uniq_party_profile_code', columns: ['group_code'])]
class PartyProfile
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 12)]
    private string $groupCode = '';

    /** Site officiel de la formation (https). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $officialSite = null;

    /** Page officielle de son programme 2027 (https). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $programmeUrl = null;

    /** Date a laquelle un moderateur a verifie que les liens menent bien au site de la formation. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $linksCheckedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getGroupCode(): string { return $this->groupCode; }
    public function setGroupCode(string $v): static { $this->groupCode = mb_substr(trim($v), 0, 12); return $this; }
    public function getOfficialSite(): ?string { return $this->officialSite; }
    public function setOfficialSite(?string $v): static { $this->officialSite = self::https($v); return $this; }
    public function getProgrammeUrl(): ?string { return $this->programmeUrl; }
    public function setProgrammeUrl(?string $v): static { $this->programmeUrl = self::https($v); return $this; }
    public function getLinksCheckedAt(): ?\DateTimeImmutable { return $this->linksCheckedAt; }
    public function setLinksCheckedAt(?\DateTimeImmutable $v): static { $this->linksCheckedAt = $v; return $this; }

    /** Seule une adresse https:// sans identifiants est conservee. */
    private static function https(?string $v): ?string
    {
        $v = trim((string) $v);

        return preg_match('~^https://[^\s/@]+\.[^\s/@]+~i', $v) ? mb_substr($v, 0, 255) : null;
    }
}
