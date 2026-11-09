<?php

namespace App\Entity;

use App\Repository\GratitudeRepository;
use Doctrine\ORM\Mapping as ORM;

/** "Merci pour ce dialogue" : un remerciement par personne et par reponse, jamais envers soi-meme. */
#[ORM\Entity(repositoryClass: GratitudeRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_gratitude_reply_user', columns: ['reply_id', 'user_id'])]
class Gratitude
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private DiscussionReply $reply;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(DiscussionReply $reply, User $user)
    {
        $this->reply = $reply;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getReply(): DiscussionReply { return $this->reply; }
    public function getUser(): User { return $this->user; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
