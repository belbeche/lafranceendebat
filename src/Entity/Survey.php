<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Enquete citoyenne : brouillon, apercu obligatoire, publication, envoi aux abonnes confirmes, cloture. */
#[ORM\Entity]
#[ORM\Index(columns: ['status'])]
class Survey
{
    public const DRAFT = 'draft';
    public const OPEN = 'open';
    public const CLOSED = 'closed';
    public const TYPES = ['choix' => 'Un seul choix', 'multiple' => 'Plusieurs choix', 'oui-non' => 'Oui ou non', 'echelle' => 'Échelle de 1 à 5', 'texte' => 'Réponse libre'];
    public const MIN_PUBLIC_RESPONSES = 5;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 160)]
    private string $title = '';
    #[ORM\Column(type: Types::TEXT)]
    private string $intro = '';
    /** @var list<array{type:string,label:string,required:bool,options:list<string>}> */
    #[ORM\Column(type: Types::JSON)]
    private array $questions = [];
    #[ORM\Column(length: 10)]
    private string $status = self::DRAFT;
    #[ORM\Column]
    private bool $resultsPublic = true;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $previewedAt = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $openedAt = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closesAt = null;
    /** none | sending | done : envoi de l'invitation par e-mail aux abonnes confirmes. */
    #[ORM\Column(length: 10)]
    private string $sendStatus = 'none';
    #[ORM\Column]
    private int $sendCursor = 0;
    #[ORM\Column]
    private int $sentCount = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): static { $this->title = mb_substr(trim($t), 0, 160); $this->touch(); return $this; }
    public function getIntro(): string { return $this->intro; }
    public function setIntro(string $t): static { $this->intro = trim($t); $this->touch(); return $this; }
    public function getQuestions(): array { return $this->questions; }
    public function setQuestions(array $q): static { $this->questions = array_values($q); $this->touch(); return $this; }
    public function getStatus(): string { return $this->status; }
    public function isDraft(): bool { return self::DRAFT === $this->status; }
    public function isOpen(): bool { return self::OPEN === $this->status && (!$this->closesAt || $this->closesAt > new \DateTimeImmutable()); }
    public function isResultsPublic(): bool { return $this->resultsPublic; }
    public function setResultsPublic(bool $b): static { $this->resultsPublic = $b; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $u): static { $this->createdBy = $u; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getOpenedAt(): ?\DateTimeImmutable { return $this->openedAt; }
    public function getClosesAt(): ?\DateTimeImmutable { return $this->closesAt; }
    public function setClosesAt(?\DateTimeImmutable $d): static { $this->closesAt = $d; return $this; }
    public function getPreviewedAt(): ?\DateTimeImmutable { return $this->previewedAt; }
    /** L'apercu n'est valable que pour la version actuelle : toute modification l'annule. */
    public function isPreviewed(): bool { return $this->previewedAt && (!$this->updatedAt || $this->previewedAt >= $this->updatedAt); }
    public function markPreviewed(): static { $this->previewedAt = new \DateTimeImmutable(); return $this; }
    public function publish(): static { $this->status = self::OPEN; $this->openedAt ??= new \DateTimeImmutable(); return $this; }
    public function close(): static { $this->status = self::CLOSED; return $this; }
    public function getSendStatus(): string { return $this->sendStatus; }
    public function queueSend(): static { if ('none' === $this->sendStatus) { $this->sendStatus = 'sending'; } return $this; }
    public function finishSend(): static { $this->sendStatus = 'done'; return $this; }
    public function getSendCursor(): int { return $this->sendCursor; }
    public function setSendCursor(int $c): static { $this->sendCursor = $c; return $this; }
    public function getSentCount(): int { return $this->sentCount; }
    public function incSent(): static { ++$this->sentCount; return $this; }

    private function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    /**
     * Syntaxe d'une question par ligne : « type | texte de la question | option 1 ; option 2 ». Un « * » apres le type la rend obligatoire.
     * @return array{0:list<array{type:string,label:string,required:bool,options:list<string>}>,1:list<string>} questions et erreurs
     */
    public static function parse(string $text): array
    {
        $q = [];
        $err = [];
        foreach (preg_split('/\R/', trim($text)) ?: [] as $n => $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $type = mb_strtolower(rtrim($parts[0], '* '));
            $required = str_contains($parts[0], '*');
            $label = $parts[1] ?? '';
            $opts = array_values(array_filter(array_map('trim', explode(';', $parts[2] ?? '')), static fn ($o) => '' !== $o));
            $at = 'Ligne ' . ($n + 1) . ' : ';
            if (!isset(self::TYPES[$type])) {
                $err[] = $at . 'type inconnu « ' . mb_substr($parts[0], 0, 20) . ' » (' . implode(', ', array_keys(self::TYPES)) . ').';
                continue;
            }
            if (mb_strlen($label) < 3 || mb_strlen($label) > 200) {
                $err[] = $at . 'la question doit faire 3 à 200 caractères.';
                continue;
            }
            if (in_array($type, ['choix', 'multiple'], true) && (count($opts) < 2 || count($opts) > 12)) {
                $err[] = $at . 'indiquez 2 à 12 réponses possibles séparées par « ; ».';
                continue;
            }
            foreach ($opts as $o) {
                if (mb_strlen($o) > 120) {
                    $err[] = $at . 'une réponse dépasse 120 caractères.';
                    continue 2;
                }
            }
            $q[] = ['type' => $type, 'label' => $label, 'required' => $required, 'options' => 'oui-non' === $type ? ['Oui', 'Non'] : (in_array($type, ['choix', 'multiple'], true) ? $opts : [])];
        }
        if (!$q && !$err) {
            $err[] = 'Ajoutez au moins une question.';
        }
        if (count($q) > 20) {
            $err[] = 'Vingt questions au maximum.';
        }

        return [$q, $err];
    }

    /** Retranscription editable des questions (pour le formulaire d'edition). */
    public function questionsAsText(): string
    {
        return implode("\n", array_map(static fn (array $q) => $q['type'] . ($q['required'] ? '*' : '') . ' | ' . $q['label'] . (in_array($q['type'], ['choix', 'multiple'], true) ? ' | ' . implode(' ; ', $q['options']) : ''), $this->questions));
    }
}
