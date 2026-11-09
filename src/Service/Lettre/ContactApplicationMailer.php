<?php

namespace App\Service\Lettre;

use App\Entity\ActorContact;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Transmet une candidature à un contact public : origine de l'adresse, désinscription en un clic, aucun pixel espion. */
class ContactApplicationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly LettreTokens $tokens,
        private readonly string $mailFrom,
        private readonly string $mailFromName,
    ) {
    }

    public function isReady(): bool
    {
        return '' !== trim($this->mailFrom) && false !== filter_var($this->mailFrom, FILTER_VALIDATE_EMAIL);
    }

    public function send(User $applicant, ActorContact $c, string $mission, string $message, string $replyTo): void
    {
        $id = (int) $c->getId();
        $unsub = $this->urls->generate('app_actor_unsubscribe', ['id' => $id, 'token' => $this->tokens->actorUnsubscribe($id, $c->getEmail())], UrlGeneratorInterface::ABSOLUTE_URL);
        $consent = $this->urls->generate('app_actor_consent', ['id' => $id, 'token' => $this->tokens->actorConsent($id, $c->getEmail())], UrlGeneratorInterface::ABSOLUTE_URL);
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->replyTo(new Address($replyTo))
            ->to(new Address($c->getEmail()))
            ->subject(preg_replace('/[\r\n]+/', ' ', 'Candidature à une mission : ' . $mission))
            ->htmlTemplate('emails/candidature_contact.html.twig')
            ->textTemplate('emails/candidature_contact.txt.twig')
            ->context(['mission' => $mission, 'message' => $message, 'applicantName' => $applicant->getPublicName(), 'actor' => $c, 'unsubscribeUrl' => $unsub, 'consentUrl' => $consent, 'consented' => $c->hasConsent()]);
        $h = $email->getHeaders();
        $h->addTextHeader('List-Unsubscribe', '<' . $unsub . '>');
        $h->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $h->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
        $this->mailer->send($email);
    }
}
