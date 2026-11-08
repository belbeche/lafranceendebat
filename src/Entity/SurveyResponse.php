<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Une reponse par personne et par enquete (membre connecte, ou navigateur anonyme). Aucune adresse IP n'est conservee. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_survey_respondent', columns: ['survey_id', 'respondent_key'])]
class SurveyResponse
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Survey::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Survey $survey;
    #[ORM\Column(length: 80)]
    private string $respondentKey;
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $profile;
    /** @var array<int,mixed> index de question => valeur(s) */
    #[ORM\Column(type: 'json')]
    private array $answers;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Survey $s, string $key, ?string $profile, array $answers)
    {
        $this->survey = $s;
        $this->respondentKey = $key;
        $this->profile = $profile;
        $this->answers = $answers;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSurvey(): Survey { return $this->survey; }
    public function getProfile(): ?string { return $this->profile; }
    public function getAnswers(): array { return $this->answers; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
