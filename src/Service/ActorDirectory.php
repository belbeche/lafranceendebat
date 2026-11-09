<?php

namespace App\Service;

use App\Entity\RoleRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Acteurs publics concernés par un besoin : élus, associations, prestataires, avec leur organisation vérifiée. */
class ActorDirectory
{
    public const LABELS = ['ROLE_ELU' => 'Élu ou institution', 'ROLE_ASSOCIATION' => 'Association', 'ROLE_ACTOR' => 'Prestataire ou expert', 'ROLE_JOURNALIST' => 'Journaliste indépendant', 'ROLE_MODERATOR' => 'Médiateur'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @param string[] $roles @return list<array{user: User, label: string, organisation: ?string, territory: ?string}> */
    public function forRoles(array $roles, int $limit = 30, ?User $exclude = null): array
    {
        $qb = $this->em->getRepository(User::class)->createQueryBuilder('u');
        $or = $qb->expr()->orX();
        foreach ($roles as $i => $r) {
            $or->add("u.roles LIKE :r$i");
            $qb->setParameter("r$i", '%"' . $r . '"%');
        }
        $qb->andWhere($or)->orderBy('u.id', 'ASC')->setMaxResults($limit);
        if ($exclude) {
            $qb->andWhere('u != :me')->setParameter('me', $exclude);
        }
        $out = [];
        foreach ($qb->getQuery()->getResult() as $u) {
            $roleKey = array_values(array_intersect(['ROLE_ELU', 'ROLE_ASSOCIATION', 'ROLE_ACTOR', 'ROLE_JOURNALIST', 'ROLE_MODERATOR'], $u->getRoles()))[0] ?? 'ROLE_ACTOR';
            $rr = $this->em->getRepository(RoleRequest::class)->findOneBy(['user' => $u, 'role' => $roleKey, 'status' => RoleRequest::STATUS_APPROVED], ['id' => 'DESC']);
            $out[] = ['user' => $u, 'label' => self::LABELS[$roleKey] ?? 'Acteur', 'organisation' => $rr?->getOrganisation(), 'territory' => $rr?->getTerritory()];
        }

        return $out;
    }

    /** Texte normalise (minuscules, sans accents) pour la recherche de competences. */
    public static function normalize(string $t): string
    {
        $t = function_exists('transliterator_transliterate') ? (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $t) : mb_strtolower(strtr($t, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ù' => 'u', 'ç' => 'c', 'ï' => 'i', 'ë' => 'e']));

        return $t;
    }

    /**
     * Acteurs d'un bon profil classes par correspondance de competences : on cherche les mots-cles du besoin dans
     * l'organisation, la motivation et le territoire de la demande de role approuvee, et dans les prestations publiees.
     * Sans mot-cle (besoin general), tous les acteurs du profil sont proposes.
     *
     * @param string[] $roles
     * @param string[] $keywords
     *
     * @return array{matches: list<array>, eligible: int, generic: bool}
     */
    public function match(array $roles, array $keywords, int $limit = 3): array
    {
        $base = $this->forRoles($roles, 60);
        if (!$keywords) {
            return ['matches' => array_slice($base, 0, $limit), 'eligible' => count($base), 'generic' => true];
        }
        $scored = [];
        foreach ($base as $card) {
            $u = $card['user'];
            $text = ($card['organisation'] ?? '') . ' ' . ($card['territory'] ?? '');
            foreach ($this->em->getRepository(RoleRequest::class)->findBy(['user' => $u, 'status' => RoleRequest::STATUS_APPROVED]) as $rr) {
                $text .= ' ' . $rr->getOrganisation() . ' ' . $rr->getMotivation() . ' ' . $rr->getTerritory();
            }
            foreach ($this->em->getRepository(\App\Entity\ServiceOffer::class)->findBy(['provider' => $u, 'status' => \App\Entity\ServiceOffer::STATUS_PUBLISHED]) as $o) {
                $text .= ' ' . $o->getTitle() . ' ' . $o->getDescription() . ' ' . $o->getCategoryLabel();
            }
            $text = self::normalize($text);
            $hits = [];
            foreach ($keywords as $k) {
                if (str_contains($text, self::normalize($k))) {
                    $hits[] = $k;
                }
            }
            if ($hits) {
                $scored[] = $card + ['skills' => $hits, 'score' => count($hits)];
            }
        }
        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['matches' => array_slice($scored, 0, $limit), 'eligible' => count($base), 'generic' => false];
    }
}
