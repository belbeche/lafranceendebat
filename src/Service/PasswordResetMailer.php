<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/** Envoie le lien de réinitialisation : utilisé par la page publique et par le back-office (administrateurs et modérateurs). */
class PasswordResetMailer
{
    public function __construct(
        private ResetPasswordHelperInterface $helper,
        private \App\Repository\ResetPasswordRequestRepository $requests,
        private \Psr\Log\LoggerInterface $logger,
        private MailerInterface $mailer,
        #[Autowire('%env(MAIL_FROM)%')] private string $mailFrom,
    ) {
    }

    /** @return ResetPasswordToken|null null si une demande récente existe déjà ou si l'envoi échoue */
    public function send(User $user, bool $force = false, ?string &$error = null): ?ResetPasswordToken
    {
        if ($force) {
            // Back-office : on remplace toute demande précédente (sinon la limite anti-abus bloque l'envoi).
            $this->requests->removeRequests($user);
        }
        try {
            $token = $this->helper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $error = 'throttled';

            return null;
        }
        try {
            $this->mailer->send((new TemplatedEmail())
                ->from(new Address('' !== $this->mailFrom ? $this->mailFrom : 'noreply@invalid.example', 'La France en Débat'))
                ->to($user->getEmail())
                ->subject('Réinitialisation de votre mot de passe | La France en Débat')
                ->htmlTemplate('reset_password/email.html.twig')
                ->context(['resetToken' => $token, 'user' => $user]));
        } catch (\Throwable $e) {
            $error = 'mail';
            $this->logger->error('Envoi du lien de réinitialisation impossible : ' . $e->getMessage());

            return null;
        }

        return $token;
    }
}
