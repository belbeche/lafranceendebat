<?php

namespace App\Service\Security;

use App\Entity\LoginEvent;
use App\Entity\User;
use App\Entity\UserEvent;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/** Ecrit le journal de securite. Ne fait JAMAIS echouer la requete de l'utilisateur. Pays : en-tete du CDN (CF-IPCountry), aucun appel a un service tiers. */
class UserJournal
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly RequestStack $requests, private readonly LoggerInterface $logger, private readonly ?AdminAlerter $alerter = null)
    {
    }

    public function record(string $type, ?User $user, ?string $label = null, ?string $detail = null, bool $withPath = false, ?string $path = null): void
    {
        try {
            $r = $this->requests->getCurrentRequest();
            $country = $r ? strtoupper((string) $r->headers->get('CF-IPCountry', '')) : '';
            $country = preg_match('/^[A-Z]{2}$/', $country) && !in_array($country, ['XX', 'T1'], true) ? $country : null;
            $city = $r ? trim((string) $r->headers->get('CF-IPCity', '')) : '';
            $this->em->persist($event = new UserEvent(
                $type, $user, $label ?? ($user ? DisposableEmailPolicy::mask((string) $user->getEmail()) : null), $detail,
                $path ?? ($withPath && $r ? $r->getPathInfo() : null),
                $r?->getClientIp(), $country, '' !== $city ? $city : null,
                LoginEvent::describe($r?->headers->get('User-Agent')),
            ));
            $this->em->flush();
            $this->alerter?->notify($event);
        } catch (\Throwable $e) {
            $this->logger->warning('Journal de securite non ecrit : ' . $e->getMessage());
        }
    }

    /** Nombre d'evenements d'un type depuis une adresse IP sur une periode. */
    public function countByIp(string $type, ?string $ip, string $since): int
    {
        if (!$ip) {
            return 0;
        }

        return (int) $this->em->createQuery('SELECT COUNT(e.id) FROM App\Entity\UserEvent e WHERE e.type = :t AND e.ip = :ip AND e.at > :s')
            ->setParameters(['t' => $type, 'ip' => $ip, 's' => new \DateTimeImmutable($since)])->getSingleScalarResult();
    }

    public function countForUser(string $type, User $user, string $since): int
    {
        return (int) $this->em->createQuery('SELECT COUNT(e.id) FROM App\Entity\UserEvent e WHERE e.type = :t AND e.user = :u AND e.at > :s')
            ->setParameters(['t' => $type, 'u' => $user, 's' => new \DateTimeImmutable($since)])->getSingleScalarResult();
    }
}
