<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Message echange entre l'acheteur et le prestataire d'une commande. Toutes les demandes passent par la plateforme. */
#[ORM\Entity]
#[ORM\Table(name: 'order_message')]
#[ORM\Index(columns: ['order_id', 'created_at'], name: 'idx_order_message_order')]
class OrderMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'order_id', nullable: false, onDelete: 'CASCADE')]
    private ServiceOrder $order;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $author;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(ServiceOrder $order, User $author, string $body)
    {
        $this->order = $order;
        $this->author = $author;
        $this->body = mb_substr(trim($body), 0, 2000);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrder(): ServiceOrder { return $this->order; }
    public function getAuthor(): User { return $this->author; }
    public function getBody(): string { return $this->body; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
