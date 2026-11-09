<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Vote d'un groupe politique de l'Assemblée sur un scrutin public, rattaché à une affirmation de la page Positions.
 * Les chiffres viennent du scrutin officiel (voir ScrutinImporter) ; la position du groupe est sa majorité interne,
 * jamais une interprétation. Un même scrutin ne peut être enregistré qu'une fois par groupe et par affirmation.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_group_vote', columns: ['statement_id', 'scrutin', 'group_code'])]
class GroupVote
{
    public const FOR = 'pour';
    public const AGAINST = 'contre';
    public const ABSTAIN = 'abstention';
    public const LABELS = [self::FOR => 'Pour', self::AGAINST => 'Contre', self::ABSTAIN => 'Abstention'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PositionStatement $statement;
    #[ORM\Column]
    private int $scrutin;
    #[ORM\Column(length: 300)]
    private string $scrutinUrl;
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $votedAt = null;
    #[ORM\Column(length: 40)]
    private string $groupCode;
    #[ORM\Column(length: 120)]
    private string $groupName;
    #[ORM\Column]
    private int $pour = 0;
    #[ORM\Column]
    private int $contre = 0;
    #[ORM\Column]
    private int $abstention = 0;

    public function __construct(PositionStatement $s, int $scrutin, string $scrutinUrl, string $groupCode, string $groupName, int $pour, int $contre, int $abstention, ?\DateTimeImmutable $votedAt = null)
    {
        $this->statement = $s;
        $this->scrutin = $scrutin;
        $this->scrutinUrl = mb_substr($scrutinUrl, 0, 300);
        $this->groupCode = mb_substr(trim(strip_tags($groupCode)), 0, 40);
        $this->groupName = mb_substr(trim(strip_tags($groupName)), 0, 120);
        $this->pour = max(0, $pour);
        $this->contre = max(0, $contre);
        $this->abstention = max(0, $abstention);
        $this->votedAt = $votedAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getStatement(): PositionStatement { return $this->statement; }
    public function getScrutin(): int { return $this->scrutin; }
    public function getScrutinUrl(): string { return $this->scrutinUrl; }
    public function getVotedAt(): ?\DateTimeImmutable { return $this->votedAt; }
    public function getGroupCode(): string { return $this->groupCode; }
    public function getGroupName(): string { return $this->groupName; }
    public function getPour(): int { return $this->pour; }
    public function getContre(): int { return $this->contre; }
    public function getAbstention(): int { return $this->abstention; }
    public function getTotal(): int { return $this->pour + $this->contre + $this->abstention; }

    /** Position du groupe : la plus fréquente de ses membres. En cas d'égalité, "abstention" (pas de position nette). Aucun votant : null. */
    public function getPosition(): ?string
    {
        if (0 === $this->getTotal()) {
            return null;
        }
        $max = max($this->pour, $this->contre, $this->abstention);
        $top = array_keys(array_filter([self::FOR => $this->pour, self::AGAINST => $this->contre, self::ABSTAIN => $this->abstention], static fn (int $n): bool => $n === $max));

        return 1 === count($top) ? $top[0] : self::ABSTAIN;
    }

    public function getPositionLabel(): string { return null === $this->getPosition() ? 'Pas de vote' : self::LABELS[$this->getPosition()]; }
}
