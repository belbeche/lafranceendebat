<?php

namespace App\Service;

use App\Entity\ModerationIncident;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Signale les propos bloqués : trace pour l'équipe, e-mail aux modérateurs et administrateurs,
 * e-mail pédagogique à l'auteur. Ne s'active que sur une soumission (POST) d'un membre connecté.
 * Limites anti-bruit : 1 e-mail à l'équipe par membre et par heure (sauf gravité haute), 1 e-mail au membre par jour.
 */
class ModerationReporter
{
    public const HIGH = 'haute';
    public const NORMAL = 'normale';
    public const REPEAT_THRESHOLD = 3;

    /** @var array<string,true> */
    private array $seen = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly RequestStack $requests,
        private readonly TransactionalMailer $mailer,
    ) {
    }

    /** @param string[] $reasons */
    public function report(array $reasons, string $text): void
    {
        $request = $this->requests->getCurrentRequest();
        $user = $this->security->getUser();
        if (!$request || !$request->isMethod('POST') || !$user instanceof User || $reasons === []) {
            return;
        }
        $key = md5($text . '|' . implode(',', $reasons));
        if (isset($this->seen[$key])) {
            return;
        }
        $this->seen[$key] = true;

        try {
            $high = (bool) array_intersect($reasons, ['menace', 'haine']);
            $severity = $high ? self::HIGH : self::NORMAL;
            $context = (string) ($request->attributes->get('_route') ?? $request->getPathInfo());
            $since = new \DateTimeImmutable('-1 hour');
            $recentTeamMail = (int) $this->em->createQuery('SELECT COUNT(i.id) FROM App\Entity\ModerationIncident i WHERE i.user = :u AND i.createdAt > :d')
                ->setParameter('u', $user)->setParameter('d', $since)->getSingleScalarResult();
            $dayAgo = new \DateTimeImmutable('-1 day');
            $recentUserMail = (int) $this->em->createQuery('SELECT COUNT(i.id) FROM App\Entity\ModerationIncident i WHERE i.user = :u AND i.createdAt > :d')
                ->setParameter('u', $user)->setParameter('d', $dayAgo)->getSingleScalarResult();

            $incident = new ModerationIncident($user, $context, $reasons, $severity, $text);
            $this->em->persist($incident);
            $this->em->flush();

            $month = (int) $this->em->createQuery('SELECT COUNT(i.id) FROM App\Entity\ModerationIncident i WHERE i.user = :u AND i.createdAt > :d')
                ->setParameter('u', $user)->setParameter('d', new \DateTimeImmutable('-30 days'))->getSingleScalarResult();
            $repeat = $month >= self::REPEAT_THRESHOLD;

            if ($high || $repeat || $recentTeamMail === 0) {
                foreach ($this->team() as $email) {
                    $this->mailer->moderationAlert($email, $incident, $month, $repeat);
                }
            }
            if ($recentUserMail === 0) {
                $this->mailer->moderationWarning($user, $reasons, $month);
            }
        } catch (\Throwable $e) {
            error_log('ModerationReporter: ' . $e->getMessage());
        }
    }

    /** Cas douteux relevé par l'IA : le texte est publié, mais une trace "à vérifier" est ajoutée pour les modérateurs (sans e-mail). */
    public function flagForReview(string $text, array $reasons): void
    {
        $request = $this->requests->getCurrentRequest();
        $user = $this->security->getUser();
        if (!$request || !$request->isMethod('POST') || !$user instanceof User) {
            return;
        }
        try {
            $this->em->persist(new ModerationIncident($user, (string) ($request->attributes->get('_route') ?? $request->getPathInfo()), array_merge(['a_verifier'], $reasons), 'revue', $text));
            $this->em->flush();
        } catch (\Throwable $e) {
            error_log('ModerationReporter: ' . $e->getMessage());
        }
    }

    /** @return string[] */
    private function team(): array
    {
        $rows = $this->em->createQuery("SELECT u.email FROM App\Entity\User u WHERE u.roles LIKE '%ROLE_MODERATOR%' OR u.roles LIKE '%ROLE_ADMIN%'")->getScalarResult();

        return array_values(array_unique(array_filter(array_map(fn ($r) => $r['email'], $rows))));
    }
}
