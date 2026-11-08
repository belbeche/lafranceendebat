<?php

namespace App\Service\Security;

use App\Entity\User;
use App\Entity\UserEvent;
use App\Service\TransactionalMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/** Previent par e-mail les administrateurs actifs a chaque action sensible sur un compte. Ne fait jamais echouer la requete. */
class AdminAlerter
{
    /** Types alertes. Les pages et connexions ordinaires ne le sont pas (trop frequentes). */
    private const ALERTED = [
        UserEvent::REGISTER, UserEvent::EMAIL_VERIFIED, UserEvent::REGISTER_BLOCKED, UserEvent::IP_FLAGGED, UserEvent::LOGIN_SUSPECT,
        UserEvent::WARNING, UserEvent::RESTRICTED, UserEvent::LIFTED, UserEvent::DEACTIVATED,
    ];
    /** Types potentiellement declenches en rafale par un robot : au plus un message par heure et par adresse IP. */
    private const THROTTLED = [UserEvent::REGISTER_BLOCKED, UserEvent::LOGIN_SUSPECT, UserEvent::IP_FLAGGED];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TransactionalMailer $mails,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notify(UserEvent $e): void
    {
        if (!in_array($e->getType(), self::ALERTED, true)) {
            return;
        }
        try {
            if (in_array($e->getType(), self::THROTTLED, true)) {
                $item = $this->cache->getItem('adm_alert_' . md5($e->getType() . '|' . $e->getIp()));
                if ($item->isHit()) {
                    return;
                }
                $this->cache->save($item->set(1)->expiresAfter(3600));
            }
            $admins = $this->em->createQuery('SELECT u FROM App\Entity\User u WHERE u.roles LIKE :r AND u.isActive = true')->setParameter('r', '%ROLE_ADMIN%')->getResult();
            $user = $e->getUser();
            $lines = array_filter([
                'Événement' => $e->getTypeLabel(),
                'Compte' => $user ? trim($user->getFirstName() . ' ' . $user->getLastName()) . ' (' . $user->getEmail() . ')' : $e->getLabel(),
                'Détail' => $e->getDetail(),
                'Adresse IP' => $e->getIp(),
                'Pays' => $e->getCountry(),
                'Appareil' => $e->getDevice(),
                'Date' => $e->getAt()->format('d/m/Y H:i'),
            ]);
            /** @var User $a */
            foreach ($admins as $a) {
                $this->mails->adminAccountAlert((string) $a->getEmail(), $e->getTypeLabel(), $lines, $user?->getId());
            }
        } catch (\Throwable $ex) {
            $this->logger->warning('Alerte administrateur non envoyee : ' . $ex->getMessage());
        }
    }
}
