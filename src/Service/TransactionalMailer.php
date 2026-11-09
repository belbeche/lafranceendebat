<?php

namespace App\Service;

use App\Entity\Contact;
use App\Entity\Discussion;
use App\Entity\DiscussionReply;
use App\Entity\Proposition;
use App\Entity\RoleRequest;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * E-mails de service, envoyes immediatement pendant la requete (pas de file d'attente).
 * Un echec d'envoi est journalise mais ne fait JAMAIS echouer la requete de l'utilisateur.
 */
class TransactionalMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private LoggerInterface $logger,
        private Environment $twig,
        private string $mailFrom,
        private string $mailFromName,
        private ?InAppNotifier $inApp = null,
        private ?\Symfony\Component\HttpFoundation\UriSigner $signer = null,
    ) {
    }

    public function welcome(User $user): void
    {
        $this->send($user->getEmail(), 'Bienvenue sur La France en Débat', 'welcome', ['user' => $user]);
    }

    /** Alerte de securite : connexion depuis un appareil jamais vu. */
    public function newDeviceLogin(User $user, string $device, ?string $ip, \DateTimeInterface $at): void
    {
        $this->send($user->getEmail(), 'Nouvelle connexion à votre compte', 'new_device', ['user' => $user, 'device' => $device, 'ip' => $ip, 'at' => $at]);
    }

    public function contactReceived(Contact $contact): void
    {
        $this->send($contact->getEmail(), 'Nous avons bien reçu votre message', 'contact_ack', ['contact' => $contact]);
        $to = (string) ($this->twig->getGlobals()['publisher']['email'] ?? '') ?: $this->mailFrom;
        $this->send($to, 'Contact : ' . mb_substr((string) $contact->getSujet(), 0, 80), 'contact_admin', ['contact' => $contact], replyTo: $contact->getEmail());
    }

    public function contentDecision(Proposition|Discussion|DiscussionReply $target, bool $approved, ?string $note = null): void
    {
        $author = $target->getAuthor();
        if (!$author) {
            return;
        }
        try {
        [$label, $link] = match (true) {
            $target instanceof Proposition => [$target->getTitle(), $this->urls->generate('app_proposition_show', ['id' => $target->getId()], UrlGeneratorInterface::ABSOLUTE_URL)],
            $target instanceof Discussion => [$target->getTitle(), $this->urls->generate('app_community_show', ['id' => $target->getId()], UrlGeneratorInterface::ABSOLUTE_URL)],
            default => ['votre réponse dans « ' . $target->getDiscussion()?->getTitle() . ' »', $this->urls->generate('app_community_show', ['id' => $target->getDiscussion()?->getId(), '_fragment' => 'reponse-' . $target->getId()], UrlGeneratorInterface::ABSOLUTE_URL)],
        };
        $this->inApp?->notify($author, $approved ? 'Votre contribution est publiée : ' . $label : 'Votre contribution n\'a pas été publiée : ' . $label, $link);
        $this->send($author->getEmail(), $approved ? 'Votre contribution est publiée' : 'Votre contribution n\'a pas été publiée', 'decision',
            ['approved' => $approved, 'label' => $label, 'link' => $link, 'note' => $note]);
        } catch (\Throwable $e) {
            $this->logger->error('Notification de moderation impossible : {msg}', ['msg' => $e->getMessage()]);
        }
    }

    public function roleDecision(RoleRequest $rr): void
    {
        $this->inApp?->notify($rr->getUser(), 'Votre candidature « ' . $rr->getRoleLabel() . ' » a reçu une réponse', $this->urls->generate('app_hub', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $this->send($rr->getUser()->getEmail(), 'Votre candidature sur La France en Débat', 'role_decision',
            ['status' => $rr->getStatus(), 'roleLabel' => $rr->getRoleLabel(), 'note' => $rr->getDecisionNote()]);
    }

    /** Envoie le reçu de don en pièce jointe PDF. */
    public function donationReceipt(string $to, string $name, string $number, string $pdf): void
    {
        $this->send($to, 'Votre reçu de don ' . $number, 'donation_receipt', ['name' => $name, 'number' => $number], null, [$pdf, 'recu-' . $number . '.pdf']);
    }

    /** Confirmation d'une contribution de soutien (sans reçu fiscal). */
    public function contributionThanks(string $to, string $name, int $cents): void
    {
        $this->send($to, 'Merci pour votre soutien', 'contribution_thanks', ['name' => $name, 'cents' => $cents]);
    }

    /** Alerte interne aux administrateurs : une action sensible vient d'avoir lieu sur un compte. */
    public function adminAccountAlert(string $to, string $title, array $lines, ?int $userId): void
    {
        $this->send($to, '[Sécurité] ' . $title, 'admin_account_alert', ['title' => $title, 'lines' => $lines, 'userId' => $userId]);
    }

    /** Lien signe, valable 48 h, qui confirme que la personne relève bien cette adresse. */
    public function verifyEmail(User $user): void
    {
        if (!$this->signer || !$user->getId()) {
            return;
        }
        $url = $this->signer->sign($this->urls->generate('app_verify_email', ['id' => $user->getId(), 'exp' => time() + 172800], UrlGeneratorInterface::ABSOLUTE_URL));
        $this->send($user->getEmail(), 'Confirmez votre adresse e-mail', 'verify_email', ['user' => $user, 'url' => $url]);
    }

    /** Plusieurs echecs de connexion sur ce compte : prevenir le titulaire avant tout signalement. */
    public function suspiciousLogin(User $user, string $ip, \DateTimeInterface $at): void
    {
        $this->send($user->getEmail(), 'Tentatives de connexion à votre compte', 'suspicious_login', ['user' => $user, 'ip' => $ip, 'at' => $at]);
    }

    /** Compte restreint : adresse e-mail jetable. Laisse un delai pour regulariser avant desactivation. */
    public function identityWarning(User $user, int $days): void
    {
        $this->send($user->getEmail(), 'Votre compte est restreint : utilisez une identité réelle', 'identity_warning', ['user' => $user, 'days' => $days]);
    }

    /** Dernier message : le compte est desactive, les informations de connexion restent enregistrees. */
    public function accountClosed(string $to, string $reason): void
    {
        $this->send($to, 'Votre compte a été désactivé', 'account_closed', ['reason' => $reason]);
    }

    /** Avis d'inactivite (CNIL) : garder ou supprimer le compte, par lien signe valable jusqu'a la date limite. */
    public function inactivityNotice(User $user, \DateTimeInterface $deadline, int $months): void
    {
        $url = $this->signer->sign($this->urls->generate('app_inactivity', ['id' => $user->getId(), 'exp' => $deadline->getTimestamp() + 86400 * 365], UrlGeneratorInterface::ABSOLUTE_URL));
        $this->send($user->getEmail(), 'Votre compte est inactif : le garder ou le supprimer ?', 'inactivity_notice', ['user' => $user, 'url' => $url, 'deadline' => $deadline, 'months' => $months]);
    }

    /** Alerte à l'équipe de modération : un propos a été bloqué. */
    public function moderationAlert(string $to, \App\Entity\ModerationIncident $i, int $monthCount, bool $repeat): void
    {
        $this->send($to, ($repeat ? '[Récidive] ' : '[Modération] ') . 'Propos bloqué par le filtre', 'moderation_alert', ['incident' => $i, 'monthCount' => $monthCount, 'repeat' => $repeat]);
    }

    /** Rappel courtois à l'auteur d'un propos bloqué (charte de paix). */
    public function moderationWarning(User $user, array $reasons, int $monthCount): void
    {
        $this->send($user->getEmail(), 'Un de vos messages n\'a pas été publié', 'moderation_warning', ['user' => $user, 'reasons' => $reasons, 'monthCount' => $monthCount]);
    }

    public function inactivityDeleted(string $to): void
    {
        $this->send($to, 'Votre compte a été supprimé', 'inactivity_deleted', []);
    }

    private function send(?string $to, string $subject, string $template, array $context, ?string $replyTo = null, ?array $attachment = null): void
    {
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($this->mailFrom, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->mailFrom, $this->mailFromName))
                ->to($to)
                ->subject(preg_replace('/[\r\n]+/', ' ', $subject))
                ->htmlTemplate('emails/transac/' . $template . '.html.twig')
                ->context($context);
            if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $email->replyTo($replyTo);
            }
            if ($attachment) {
                $email->attach($attachment[0], $attachment[1], 'application/pdf');
            }
            $email->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Envoi e-mail de service impossible ({template}) : {msg}', ['template' => $template, 'msg' => $e->getMessage()]);
        }
    }
}
