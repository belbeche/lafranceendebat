<?php

namespace App\EventSubscriber;

use App\Entity\LoginEvent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/** Renseigne « derniere connexion » et l'historique. Les echecs ne sont notes que pour un compte existant (jamais le mot de passe saisi). */
class LoginHistorySubscriber implements EventSubscriberInterface
{
    public const SESSION_KEY = 'lfed_login_at';

    public function __construct(private readonly EntityManagerInterface $em, private readonly LoggerInterface $logger, private readonly \App\Service\TransactionalMailer $mails, private readonly \App\Service\Security\UserJournal $journal)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onSuccess', LoginFailureEvent::class => 'onFailure'];
    }

    public function onSuccess(LoginSuccessEvent $e): void
    {
        $user = $e->getUser();
        if (!$user instanceof User) {
            return;
        }
        try {
            $event = new LoginEvent($user, true, $e->getRequest()->getClientIp(), $e->getRequest()->headers->get('User-Agent'));
            $repo = $this->em->getRepository(LoginEvent::class);
            $hadLogin = $repo->count(['user' => $user, 'success' => true]) > 0;
            $known = $repo->count(['user' => $user, 'success' => true, 'device' => $event->getDevice()]) > 0;
            $user->setLastLogin(new \DateTime());
            $this->em->persist($event);
            $this->em->flush();
            $this->journal->record(\App\Entity\UserEvent::LOGIN_OK, $user, null, $event->getDevice());
            if ($e->getRequest()->hasSession()) {
                $e->getRequest()->getSession()->set(self::SESSION_KEY, time());
            }
            if ($hadLogin && !$known) {
                $this->mails->newDeviceLogin($user, (string) ($event->getDevice() ?? 'un appareil inconnu'), $event->getIp(), $event->getAt());
            }
        } catch (\Throwable $ex) {
            $this->logger->warning('Historique de connexion non enregistre : ' . $ex->getMessage());
        }
    }

    public function onFailure(LoginFailureEvent $e): void
    {
        $email = mb_strtolower(trim((string) $e->getRequest()->request->get('_username', '')));
        if ('' === $email || strlen($email) > 180) {
            return;
        }
        try {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user) {
                $this->em->persist(new LoginEvent($user, false, $e->getRequest()->getClientIp(), $e->getRequest()->headers->get('User-Agent')));
                $this->em->flush();
            }
            $label = \App\Service\Security\DisposableEmailPolicy::mask($email);
            $this->journal->record(\App\Entity\UserEvent::LOGIN_FAIL, $user, $label);
            // 5 echecs depuis la meme adresse en 30 minutes : evenement « suspect » (une seule fois) puis avertissement au titulaire du compte.
            $ip = $e->getRequest()->getClientIp();
            if (5 === $this->journal->countByIp(\App\Entity\UserEvent::LOGIN_FAIL, $ip, '-30 minutes')) {
                $this->journal->record(\App\Entity\UserEvent::LOGIN_SUSPECT, $user, $label, '5 échecs en 30 minutes');
                if ($user && 0 === $this->journal->countForUser(\App\Entity\UserEvent::WARNING, $user, '-24 hours')) {
                    $this->mails->suspiciousLogin($user, (string) $ip, new \DateTimeImmutable());
                    $this->journal->record(\App\Entity\UserEvent::WARNING, $user, null, 'Alerte tentatives de connexion répétées');
                }
            }
        } catch (\Throwable $ex) {
            $this->logger->warning('Echec de connexion non enregistre : ' . $ex->getMessage());
        }
    }
}
