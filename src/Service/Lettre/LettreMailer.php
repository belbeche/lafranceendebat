<?php

namespace App\Service\Lettre;

use App\Entity\Lettre;
use App\Entity\NewsletterSubscriber;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Construit et envoie les e-mails du module Lettre. Aucun pixel espion, aucun lien redirige. */
class LettreMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly LettreTokens $tokens,
        private readonly LettreRenderer $renderer,
        private readonly Environment $twig,
        private readonly string $mailFrom,
        private readonly string $mailFromName,
    ) {
    }

    /** Une lettre ne part que si l'expediteur ET l'adresse postale de l'editeur sont renseignes (obligation legale). */
    public function readinessProblem(): ?string
    {
        if ('' === trim($this->mailFrom) || !filter_var($this->mailFrom, FILTER_VALIDATE_EMAIL)) {
            return "L'adresse d'expédition n'est pas configurée (variable MAIL_FROM).";
        }
        $publisher = $this->twig->getGlobals()['publisher'] ?? [];
        if ('' === trim((string) ($publisher['address'] ?? ''))) {
            return "L'adresse postale de l'éditeur est obligatoire en pied de lettre : renseignez-la dans config/packages/twig.yaml.";
        }

        return null;
    }

    public function sendConfirmation(NewsletterSubscriber $s): void
    {
        $confirmUrl = $this->urls->generate('app_lettre_confirm', [
            'id' => $s->getId(),
            'token' => $this->tokens->confirm($s, time() + 86400 * 2),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->mailer->send($this->base($s)
            ->subject('Confirmez votre inscription à la lettre de La France en Débat')
            ->htmlTemplate('emails/lettre_confirm.html.twig')
            ->textTemplate('emails/lettre_confirm.txt.twig')
            ->context(['confirmUrl' => $confirmUrl]));
    }

    /** Invitation a une enquete : meme gabarit sobre, desinscription en un clic. */
    public function sendSurvey(\App\Entity\Survey $survey, NewsletterSubscriber $s): void
    {
        $url = $this->urls->generate('app_survey_show', ['id' => $survey->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $unsubUrl = $this->unsubscribeUrl($s);
        $body = trim($survey->getIntro()) . "\n\nRépondre à l'enquête (quelques minutes, sans compte) :\n" . $url;
        $email = $this->base($s)
            ->subject('Enquête : ' . $survey->getTitle())
            ->htmlTemplate('emails/lettre.html.twig')
            ->textTemplate('emails/lettre.txt.twig')
            ->context(['subject' => $survey->getTitle(), 'bodyHtml' => $this->renderer->toHtml($body), 'bodyText' => $body, 'unsubscribeUrl' => $unsubUrl]);
        $h = $email->getHeaders();
        $h->addTextHeader('List-Unsubscribe', '<' . $unsubUrl . '>');
        $h->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $h->addTextHeader('Precedence', 'bulk');
        $h->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
        $this->mailer->send($email);
    }

    public function sendLettre(Lettre $lettre, NewsletterSubscriber $s, bool $test = false): void
    {
        $unsubUrl = $this->unsubscribeUrl($s);
        $email = $this->base($s)
            ->subject(($test ? '[TEST] ' : '') . $lettre->getSubject())
            ->htmlTemplate('emails/lettre.html.twig')
            ->textTemplate('emails/lettre.txt.twig')
            ->context([
                'subject' => $lettre->getSubject(),
                'bodyHtml' => $this->renderer->toHtml((string) $lettre->getBody()),
                'bodyText' => (string) $lettre->getBody(),
                'unsubscribeUrl' => $unsubUrl,
            ]);
        $h = $email->getHeaders();
        $h->addTextHeader('List-Unsubscribe', '<' . $unsubUrl . '>');
        $h->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $h->addTextHeader('Precedence', 'bulk');
        $h->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
        $this->mailer->send($email);
    }

    /** Demarchage d'un acteur public : meme sobriete (pas de pixel, pas de lien redirige) + origine de l'adresse et desinscription en un clic. */
    public function sendToActor(Lettre $lettre, \App\Entity\ActorContact $a, bool $test = false, ?string $to = null): void
    {
        $unsubUrl = $this->urls->generate('app_actor_unsubscribe', [
            'id' => $a->getId() ?? 0,
            'token' => $this->tokens->actorUnsubscribe((int) $a->getId(), $a->getEmail()),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
        $consentUrl = $this->urls->generate('app_actor_consent', ['id' => $a->getId() ?? 0, 'token' => $this->tokens->actorConsent((int) $a->getId(), $a->getEmail())], UrlGeneratorInterface::ABSOLUTE_URL);
        $personalBody = ActorMessageTemplates::apply((string) $lettre->getBody(), $a);
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->to(new Address($to ?? $a->getEmail()))
            ->subject(($test ? '[TEST] ' : '') . $lettre->getSubject())
            ->htmlTemplate('emails/lettre_acteur.html.twig')
            ->textTemplate('emails/lettre_acteur.txt.twig')
            ->context([
                'subject' => $lettre->getSubject(),
                'bodyHtml' => $this->renderer->toHtml($personalBody),
                'bodyText' => $personalBody,
                'unsubscribeUrl' => $unsubUrl,
                'actor' => $a,
                'invitation' => $lettre->isInvitation(),
                'consentUrl' => $consentUrl,
            ]);
        $h = $email->getHeaders();
        $h->addTextHeader('List-Unsubscribe', '<' . $unsubUrl . '>');
        $h->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $h->addTextHeader('Precedence', 'bulk');
        $h->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
        $this->mailer->send($email);
    }

    public function unsubscribeUrl(NewsletterSubscriber $s): string
    {
        return $this->urls->generate('app_lettre_unsubscribe', [
            'id' => $s->getId(),
            'token' => $this->tokens->unsubscribe($s),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function base(NewsletterSubscriber $s): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from(new Address($this->mailFrom, $this->mailFromName))
            ->to(new Address((string) $s->getEmail()));
    }
}
