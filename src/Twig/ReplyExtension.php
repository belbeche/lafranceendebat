<?php

namespace App\Twig;

use App\Entity\RightOfReply;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** published_replies('actualite', 12) : les droits de reponse publies pour un contenu, du plus ancien au plus recent. */
class ReplyExtension extends AbstractExtension
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('published_replies', $this->published(...))];
    }

    /** @return list<RightOfReply> */
    public function published(string $type, ?int $id): array
    {
        if (null === $id || !in_array($type, RightOfReply::TARGETS, true)) {
            return [];
        }

        return $this->em->getRepository(RightOfReply::class)->findBy(['targetType' => $type, 'targetId' => $id, 'status' => RightOfReply::PUBLISHED], ['publishedAt' => 'ASC'], 20);
    }
}
