<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Proposition rattachée à un groupe politique, avec sa source officielle.
 *
 * Deux origines, toujours affichées : "AN" (proposition de loi déposée à l'Assemblée nationale, importée des
 * données ouvertes officielles : le groupe est celui du premier signataire à la date de dépôt) et "manuel"
 * (mesure d'un programme publiée par la formation elle-même : l'URL de son site est obligatoire).
 * Une proposition de loi engage ses signataires, pas forcément tout leur parti : l'interface le rappelle.
 *
 * Chiffrage (facultatif) : coût annuel en millions d'euros, avec son niveau de crédibilité et sa source.
 * Aucun chiffre n'est inventé : sans source, la proposition reste "non chiffrée".
 */
#[ORM\Entity]
#[ORM\Table(name: 'party_proposal')]
#[ORM\UniqueConstraint(name: 'uniq_party_proposal_external', columns: ['external_id'])]
#[ORM\Index(columns: ['group_code'])]
class PartyProposal
{
    public const SOURCE_AN = 'AN';
    public const SOURCE_SENAT = 'SENAT';
    public const SOURCE_MANUAL = 'manuel';

    /** Niveaux de crédibilité d'un chiffrage. */
    public const CREDIBILITY = [
        'A' => 'Chiffrage officiel (étude d\'impact, Cour des comptes, loi de finances, Assemblée ou Sénat)',
        'B' => 'Chiffrage d\'un organisme indépendant publié (institut de recherche, think tank, avec méthode)',
        'C' => 'Estimation de l\'auteur ou citoyenne, non vérifiée',
    ];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** Identifiant dans la source (ex. PIONANR5L17B0016) ou "manuel-…". */
    #[ORM\Column(length: 60)]
    private string $externalId = '';

    #[ORM\Column(length: 10)]
    private string $source = self::SOURCE_AN;

    #[ORM\Column(length: 12)]
    private string $groupCode = '';

    #[ORM\Column(length: 160)]
    private string $groupName = '';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $groupColor = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $theme = null;

    #[ORM\Column(nullable: true)]
    private ?int $legislature = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $depositedAt = null;

    #[ORM\Column(length: 255)]
    private string $sourceUrl = '';

    /** Coût annuel estimé, en millions d'euros (positif = dépense, négatif = économie ou recette). */
    #[ORM\Column(nullable: true)]
    private ?int $costMillions = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $costCredibility = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $costSource = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $costNote = null;

    /**
     * HYPOTHESE IA (Gemini), distincte du chiffrage source ci-dessus. Jamais melangee aux niveaux A, B, C ;
     * publique seulement apres validation d'un moderateur (aiApproved). Voir ProposalAnalyst et CostModel.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $aiAnalysis = null;

    #[ORM\Column(nullable: true)]
    private ?int $aiLowMillions = null;

    #[ORM\Column(nullable: true)]
    private ?int $aiCentralMillions = null;

    #[ORM\Column(nullable: true)]
    private ?int $aiHighMillions = null;

    #[ORM\Column(nullable: true)]
    private ?int $aiReliability = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $aiModel = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $aiAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $aiApproved = false;

    #[ORM\Column]
    private bool $published = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getExternalId(): string { return $this->externalId; }
    public function setExternalId(string $v): static { $this->externalId = mb_substr($v, 0, 60); return $this; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $v): static { $this->source = in_array($v, [self::SOURCE_MANUAL, self::SOURCE_SENAT], true) ? $v : self::SOURCE_AN; return $this; }
    public function getGroupCode(): string { return $this->groupCode; }
    public function getGroupName(): string { return $this->groupName; }
    public function getGroupColor(): ?string { return $this->groupColor; }
    public function setGroup(string $code, string $name, ?string $color = null): static
    {
        $this->groupCode = mb_substr(trim($code), 0, 12);
        $this->groupName = mb_substr(trim($name), 0, 160);
        $this->groupColor = ($color && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) ? $color : null;

        return $this;
    }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = mb_substr(trim($v), 0, 255); return $this; }
    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $v): static { $v = trim((string) $v); $this->summary = '' === $v ? null : mb_substr($v, 0, 2000); return $this; }
    public function getTheme(): ?string { return $this->theme; }
    public function setTheme(?string $v): static { $this->theme = ($v && in_array($v, Proposition::THEMES, true)) ? $v : null; return $this; }
    public function getLegislature(): ?int { return $this->legislature; }
    public function setLegislature(?int $v): static { $this->legislature = $v; return $this; }
    public function getDepositedAt(): ?\DateTimeImmutable { return $this->depositedAt; }
    public function setDepositedAt(?\DateTimeImmutable $v): static { $this->depositedAt = $v; return $this; }
    public function getSourceUrl(): string { return $this->sourceUrl; }
    /** Seule une adresse https:// est acceptée : sans source vérifiable, la proposition n'est pas publiable. */
    public function setSourceUrl(string $v): static { $v = trim($v); $this->sourceUrl = preg_match('~^https://~i', $v) ? mb_substr($v, 0, 255) : ''; return $this; }
    public function hasValidSource(): bool { return '' !== $this->sourceUrl; }
    public function getCostMillions(): ?int { return $this->costMillions; }
    public function getCostCredibility(): ?string { return $this->costCredibility; }
    public function getCostSource(): ?string { return $this->costSource; }
    public function getCostNote(): ?string { return $this->costNote; }
    public function isCosted(): bool { return null !== $this->costMillions && null !== $this->costCredibility; }

    /** Un chiffrage n'est enregistré qu'avec un niveau de crédibilité ET une source https:// : sinon la proposition reste non chiffrée. */
    public function setCost(?int $millions, ?string $credibility, ?string $source, ?string $note = null): static
    {
        $source = trim((string) $source);
        if (null === $millions || !isset(self::CREDIBILITY[(string) $credibility]) || !preg_match('~^https://~i', $source)) {
            $this->costMillions = $this->costCredibility = $this->costSource = $this->costNote = null;

            return $this;
        }
        $this->costMillions = max(-1000000, min(1000000, $millions));
        $this->costCredibility = (string) $credibility;
        $this->costSource = mb_substr($source, 0, 255);
        $note = trim((string) $note);
        $this->costNote = '' === $note ? null : mb_substr($note, 0, 255);

        return $this;
    }

    /** Fiabilite d'un chiffrage SOURCE selon son niveau (sert d'indicateur de lecture, pas de note politique). */
    public function sourcedReliability(): ?int
    {
        if (!$this->isCosted()) {
            return null;
        }

        return ['A' => 90, 'B' => 70, 'C' => 35][$this->costCredibility] + (null !== $this->costNote ? 5 : 0);
    }

    /** @param array<string,mixed>|null $analysis résultat validé de ProposalAnalyst::parse() ; toute nouvelle analyse repasse en attente de validation */
    public function setAiAnalysis(?array $analysis, ?string $model = null): static
    {
        $this->aiApproved = false;
        $this->aiAt = null === $analysis ? null : new \DateTimeImmutable();
        $this->aiModel = null === $analysis ? null : mb_substr((string) $model, 0, 40);
        $this->aiAnalysis = $analysis;
        $sc = $analysis['scenarios'] ?? null;
        $ok = null !== $sc && !empty($analysis['costable']);
        $this->aiLowMillions = $ok ? (int) $sc['low'] : null;
        $this->aiCentralMillions = $ok ? (int) $sc['central'] : null;
        $this->aiHighMillions = $ok ? (int) $sc['high'] : null;
        $this->aiReliability = null === $analysis ? null : (int) ($analysis['reliability'] ?? 0);

        return $this;
    }

    public function getAiAnalysis(): ?array { return $this->aiAnalysis; }
    public function getAiLowMillions(): ?int { return $this->aiLowMillions; }
    public function getAiCentralMillions(): ?int { return $this->aiCentralMillions; }
    public function getAiHighMillions(): ?int { return $this->aiHighMillions; }
    public function getAiReliability(): ?int { return $this->aiReliability; }
    public function getAiModel(): ?string { return $this->aiModel; }
    public function getAiAt(): ?\DateTimeImmutable { return $this->aiAt; }
    public function hasAiEstimate(): bool { return null !== $this->aiCentralMillions; }
    public function isAiApproved(): bool { return $this->aiApproved; }
    /** Seule une analyse validée par un modérateur est visible du public. */
    public function setAiApproved(bool $v): static { $this->aiApproved = $v && null !== $this->aiAnalysis; return $this; }
    public function isAiPublic(): bool { return $this->aiApproved && null !== $this->aiAnalysis; }

    public function isPublished(): bool { return $this->published; }
    public function setPublished(bool $v): static { $this->published = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
