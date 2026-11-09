<?php

namespace App\Service;

use App\Entity\Proposition;
use App\Entity\Revendication;
use App\Entity\RoleRequest;
use App\Entity\ServiceOrder;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\HttpFoundation\UriSigner;

/** Notifications transactionnelles : jamais de contenu du message, seulement un lien vers la plateforme. */
class NotificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
        private readonly InAppNotifier $inApp,
        #[Autowire(service: 'cache.app')] private readonly AdapterInterface $cache,
        private readonly UriSigner $signer,
        private readonly \Doctrine\ORM\EntityManagerInterface $em,
        #[Autowire('%env(MAIL_FROM)%')] private readonly string $mailFrom,
        #[Autowire('%env(MAIL_FROM_NAME)%')] private readonly string $mailFromName,
        #[Autowire('%env(PUBLISHER_EMAIL)%')] private readonly string $adminEmail,
    ) {
    }

    public function newMessage(ServiceOrder $order, User $author): void
    {
        $to = $author === $order->getBuyer() ? $order->getOffer()->getProvider() : $order->getBuyer();
        $this->send($to, 'Nouveau message sur votre commande', 'Vous avez reçu un nouveau message à propos de « '.$order->getOffer()->getTitle().' ».', $this->urls->generate('app_order_messages', ['id' => $order->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function roleRequestReceived(RoleRequest $rr): void
    {
        $u = $rr->getUser();
        $label = $rr->getRoleLabel();
        $this->toAdmin('Nouvelle candidature : '.$label, $u->getPublicName().' ('.$u->getEmail().') candidate au droit « '.$label." ».\n\nMotivation :\n".$rr->getMotivation(), $this->urls->generate('admin_role_requests', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $this->send($u, 'Votre candidature est bien reçue', 'Nous avons bien reçu votre candidature au droit « '.$label.' ». Un administrateur l\'examine et vous répond par e-mail.', $this->urls->generate('app_role_request', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function propositionSubmitted(Proposition $p): void
    {
        $a = $p->getAuthor();
        $this->toAdmin('Nouvelle proposition à relire', 'Proposition « '.$p->getTitle().' » déposée'.($a ? ' par '.$a->getPublicName() : '').'.', $this->urls->generate('admin_moderation', [], UrlGeneratorInterface::ABSOLUTE_URL));
        if ($a) {
            $this->send($a, 'Votre proposition est bien reçue', 'Votre proposition « '.$p->getTitle().' » est enregistrée. Elle sera publiée après relecture, en général sous 48 h.', $this->urls->generate('app_my_propositions', [], UrlGeneratorInterface::ABSOLUTE_URL));
        }
    }

    public function claimSubmitted(Revendication $r): void
    {
        $this->toAdmin('Nouvelle revendication à relire', 'Revendication « '.$r->getTitle().' » ('.$r->getLocale().') déposée, non publiée. Source : '.$r->getSourceUrl()."\nVérifier la source, les faits et prévoir un droit de réponse avant publication.", $this->urls->generate('admin_revendication_index', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function debateSubmitted(\App\Entity\Debat $d): void
    {
        $this->toAdmin('Nouveau débat à relire', 'Débat « ' . $d->getTitle() . ' » proposé' . ($d->getProposedBy() ? ' par ' . $d->getProposedBy()->getPublicName() : '') . ', non publié. Vérifier la neutralité de l\'exposé et les sources, puis passer le statut à « ouvert ».', $this->urls->generate('admin_debat_index', [], UrlGeneratorInterface::ABSOLUTE_URL));
        if ($d->getProposedBy()) {
            $this->send($d->getProposedBy(), 'Votre débat est bien reçu', 'Votre débat « ' . $d->getTitle() . ' » est en relecture : exposé neutre, faits sourcés. Vous serez prévenu à sa publication.', $this->urls->generate('app_my_requests', [], UrlGeneratorInterface::ABSOLUTE_URL));
        }
    }

    public function eventSubmitted(\App\Entity\Event $e): void
    {
        $this->toAdmin('Nouvelle rencontre à valider', 'Rencontre « ' . $e->getName() . ' » proposée' . ($e->getProposedBy() ? ' par ' . $e->getProposedBy()->getPublicName() : '') . ', non publiée.', $this->urls->generate('admin_event_index', [], UrlGeneratorInterface::ABSOLUTE_URL));
        if ($e->getProposedBy()) {
            $this->send($e->getProposedBy(), 'Votre rencontre est bien reçue', 'Votre rencontre « ' . $e->getName() . ' » sera publiée à l\'agenda après validation.', $this->urls->generate('app_agenda', [], UrlGeneratorInterface::ABSOLUTE_URL));
        }
    }

    public function orderPaid(ServiceOrder $order): void
    {
        $t = $order->getOffer()->getTitle();
        $this->send($order->getOffer()->getProvider(), 'Nouvelle commande payée', 'La commande « '.$t.' » est payée. Vous pouvez échanger avec le client et réaliser la prestation.', $this->urls->generate('app_actor_space', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $this->send($order->getBuyer(), 'Paiement confirmé', 'Votre paiement pour « '.$t.' » est confirmé. Il est gardé en attente jusqu\'à votre confirmation de la prestation.', $this->urls->generate('app_my_orders', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $this->toAdmin('Commande payée', 'Commande #'.$order->getId().' « '.$t.' » payée.', $this->urls->generate('admin_services', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function orderReleased(ServiceOrder $order): void
    {
        $this->send($order->getOffer()->getProvider(), 'Prestation confirmée, paiement reversé', 'La prestation « '.$order->getOffer()->getTitle().' » est confirmée. Le montant net vous est reversé.', $this->urls->generate('app_actor_space', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /** Candidature à une mission : prévient l'acteur destinataire (plateforme + e-mail) avec les coordonnées du candidat. */
    public function missionApplication(\App\Entity\MissionApplication $a): void
    {
        $url = $this->urls->generate('app_received_applications', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->send($a->getRecipient(), 'Nouvelle candidature : ' . $a->getMission(), $a->getApplicant()->getPublicName() . ' souhaite contribuer. Contact : ' . $a->getEmail() . ', ' . $a->getPhone() . '. Répondez depuis votre espace : accepter ou décliner.', $url);
    }

    public function missionApplicationAnswered(\App\Entity\MissionApplication $a): void
    {
        $url = $this->urls->generate('app_my_applications', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $ok = \App\Entity\MissionApplication::ACCEPTED === $a->getStatus();
        $this->send($a->getApplicant(), ($ok ? 'Candidature acceptée : ' : 'Candidature non retenue : ') . $a->getMission(), $a->getRecipient()->getPublicName() . ($ok ? ' a accepté votre candidature.' : ' ne peut pas donner suite.') . ($a->getResponseNote() ? ' Message : ' . $a->getResponseNote() : ''), $url);
    }


    /** Un argument est publie : previent l'auteur du debat et, pour une reponse, l'auteur de l'argument concerne. Notification dans la plateforme a chaque fois, e-mail au plus une fois par 6 h et par debat. */
    public function argumentPosted(\App\Entity\Argument $a): void
    {
        $debat = $a->getDebat();
        $author = $a->getAuthor();
        if (!$debat) {
            return;
        }
        $url = $this->urls->generate('app_debat_show', ['slug' => $debat->getSlug(), '_fragment' => 'argument-' . $a->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $targets = [];
        $parentAuthor = $a->getParent()?->getAuthor();
        if ($parentAuthor && $parentAuthor !== $author) {
            $targets[$parentAuthor->getId()] = [$parentAuthor, 'Réponse à votre argument', ($author ? $author->getPublicName() : 'Un citoyen') . ' a répondu à votre argument dans le débat « ' . $debat->getTitle() . ' ».'];
        }
        $owner = $debat->getProposedBy();
        if ($owner && $owner !== $author && !isset($targets[$owner->getId()])) {
            $targets[$owner->getId()] = [$owner, 'Nouvel argument dans votre débat', 'Un nouvel argument (' . $a->getStanceLabel() . ') a été publié dans le débat « ' . $debat->getTitle() . ' ».'];
        }
        // Suivi automatique : les personnes deja intervenues dans ce debat (plafonne a 50) sont prevenues d'un nouvel argument.
        foreach (array_slice($debat->getArguments()->toArray(), 0, 200) as $other) {
            $p = $other->getAuthor();
            if ($p && $p !== $author && !isset($targets[$p->getId()]) && \count($targets) < 50) {
                $targets[$p->getId()] = [$p, 'Nouvel argument dans un débat où vous êtes intervenu', 'Un nouvel argument (' . $a->getStanceLabel() . ') a été publié dans le débat « ' . $debat->getTitle() . ' ».'];
            }
        }
        foreach ($targets as [$to, $subject, $text]) {
            $this->send($to, $subject, $text, $url, 'arg' . $debat->getId() . '_' . $to->getId());
        }
    }

    /** Changement de statut d'un debat par la moderation : previent l'auteur. */
    public function debateStatusChanged(\App\Entity\Debat $d, string $old): void
    {
        $to = $d->getProposedBy();
        if (!$to || $old === $d->getStatus()) {
            return;
        }
        $title = $d->getTitle();
        [$subject, $text] = match (true) {
            \App\Entity\Debat::STATUS_DRAFT === $old && \App\Entity\Debat::STATUS_OPEN === $d->getStatus() => ['Votre débat est publié', 'Votre débat « ' . $title . ' » est ouvert : chacun peut y déposer des arguments pour, contre ou nuancés.'],
            \App\Entity\Debat::STATUS_CLOSED === $d->getStatus() => ['Votre débat est clos', 'Le débat « ' . $title . ' » est clos.' . ($d->getOutcome() ? ' Un compte rendu est publié sur la page.' : '')],
            \App\Entity\Debat::STATUS_DRAFT === $d->getStatus() => ['Votre débat repasse en relecture', 'Le débat « ' . $title . ' » est temporairement retiré de l\'affichage pour relecture.'],
            default => ['Votre débat est de nouveau ouvert', 'Le débat « ' . $title . ' » est de nouveau ouvert aux arguments.'],
        };
        $this->send($to, $subject, $text, $this->urls->generate('app_debat_show', ['slug' => $d->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));
        if (\App\Entity\Debat::STATUS_DRAFT === $old && \App\Entity\Debat::STATUS_OPEN === $d->getStatus()) {
            $this->debatePublished($d);
        }
    }

    public function tribuneSubmitted(\App\Entity\Tribune $t): void
    {
        $this->toAdmin('Nouvelle tribune à relire', 'Tribune « ' . $t->getTitle() . ' » déposée par ' . $t->getAuthor()->getPublicName() . '. Vérifier les sources et le respect de la charte avant publication.', $this->urls->generate('admin_moderation', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $this->send($t->getAuthor(), 'Votre tribune est bien reçue', 'Votre tribune « ' . $t->getTitle() . ' » est en relecture. La décision, motivée, vous sera envoyée par e-mail.', $this->urls->generate('app_tribune_mine', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    public function tribuneDecision(\App\Entity\Tribune $t, bool $approved, ?string $note = null): void
    {
        $url = $approved ? $this->urls->generate('app_tribune_show', ['slug' => $t->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL) : $this->urls->generate('app_tribune_mine', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->send($t->getAuthor(), $approved ? 'Votre tribune est publiée' : 'Votre tribune n\'a pas été publiée', $approved ? 'Votre tribune « ' . $t->getTitle() . ' » est publiée. Les personnes citées disposent d\'un droit de réponse.' : 'Votre tribune « ' . $t->getTitle() . ' » n\'a pas été publiée. Motif : ' . $note, $url);
    }

    public function claimDecision(Revendication $r, bool $approved, ?string $note = null): void
    {
        $to = $r->getSubmittedBy();
        if (!$to) {
            return;
        }
        $url = $approved ? $this->urls->generate('app_revendication_show', ['slug' => $r->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL) : $this->urls->generate('app_my_requests', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->send($to, $approved ? 'Votre demande est publiée' : 'Votre demande n\'a pas été publiée', $approved ? 'Votre demande « ' . $r->getTitle() . ' » est publiée.' : 'Votre demande « ' . $r->getTitle() . ' » n\'a pas été publiée. Motif : ' . $note, $url);
    }

    /** Nouveau signalement : previent la moderation (jamais le contenu complet). */
    public function reportSubmitted(\App\Entity\Signalement $s): void
    {
        $this->toAdmin('Nouveau signalement à examiner', 'Un contenu de type « ' . $s->getTargetType() . ' » (n° ' . $s->getTargetId() . ') a été signalé. Aucune décision automatique : un modérateur examine.', $this->urls->generate('admin_moderation', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /** Reponse dans une discussion : previent l'auteur du fil (une fois par 6 h et par fil par e-mail). */
    public function discussionReplied(\App\Entity\Discussion $d, \App\Entity\DiscussionReply $r): void
    {
        $to = $d->getAuthor();
        if (!$to || $to === $r->getAuthor()) {
            return;
        }
        $this->send($to, 'Nouvelle réponse à votre message', ($r->getAuthor() ? $r->getAuthor()->getPublicName() : 'Un citoyen') . ' a répondu dans « ' . $d->getTitle() . ' ».', $this->urls->generate('app_community_show', ['id' => $d->getId(), '_fragment' => 'reponse-' . $r->getId()], UrlGeneratorInterface::ABSOLUTE_URL), 'disc' . $d->getId() . '_' . $to->getId());
    }

    private function toAdmin(string $subject, string $text, string $url): void
    {
        $to = filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL) ? $this->adminEmail : $this->mailFrom;
        if ('' === trim($this->mailFrom) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $this->dispatch($to, '[Admin] '.$subject, $subject, $text, $url, 'Ouvrir dans l\'administration', 'Notification réservée à l\'équipe de modération.');
    }

    /** Message direct : notification dans la plateforme, et e-mail seulement si aucun message non lu du même expéditeur n'attend déjà. */
    public function directMessage(User $to, User $from, bool $emailToo): void
    {
        $url = $this->urls->generate('app_inbox_thread', ['id' => $from->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->inApp->notify($to, 'Nouveau message de ' . $from->getPublicName(), $url);
        if ($emailToo && '' !== trim($this->mailFrom) && filter_var($to->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $this->dispatch($to->getEmail(), 'Nouveau message sur La France en Débat', 'Nouveau message', $from->getPublicName() . ' vous a écrit sur la plateforme.', $url, 'Lire et répondre', 'Les échanges restent sur la plateforme.');
        }
    }

    private function send(User $to, string $subject, string $text, string $url, ?string $throttleKey = null): void
    {
        $this->inApp->notify($to, $subject, $url);
        if ('' === trim($this->mailFrom) || !filter_var($to->getEmail(), FILTER_VALIDATE_EMAIL) || !$to->isNotifyOwnActivity()) {
            return;
        }
        if (null !== $throttleKey) {
            $item = $this->cache->getItem('mailthr_' . md5($throttleKey));
            if ($item->isHit()) {
                return;
            }
            $this->cache->save($item->set(1)->expiresAfter(6 * 3600));
        }
        $this->dispatch($to->getEmail(), $subject, $subject, $text, $url, 'Lire et répondre sur la plateforme', 'Les échanges restent sur la plateforme.', $this->unsubscribeUrl($to));
    }

    /** Lien signe : coupe tous les e-mails de notification sans connexion. */
    private function unsubscribeUrl(User $u): string
    {
        return $this->signer->sign($this->urls->generate('app_notif_unsubscribe', ['id' => $u->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /** Nouveau debat publie : e-mail aux seules personnes qui l'ont demande (opt-in), par lots plafonnes. */
    public function debatePublished(\App\Entity\Debat $d): void
    {
        if ('' === trim($this->mailFrom)) {
            return;
        }
        $url = $this->urls->generate('app_debat_show', ['slug' => $d->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        $users = $this->em->getRepository(User::class)->createQueryBuilder('u')->andWhere('u.notifyNewDebates = true')->andWhere('u.isActive = true')->setMaxResults(300)->getQuery()->getResult();
        foreach ($users as $u) {
            if ($u === $d->getProposedBy() || !filter_var($u->getEmail(), FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $this->dispatch($u->getEmail(), 'Nouveau débat : ' . $d->getTitle(), 'Nouveau débat', 'Un nouveau débat vient d\'être ouvert : « ' . $d->getTitle() . ' » (' . $d->getTheme() . '). Venez y déposer un argument, pour, contre ou nuancé.', $url, 'Lire le débat', 'Vous recevez cet e-mail car vous avez demandé à être prévenu des nouveaux débats.', $this->unsubscribeUrl($u));
        }
    }

    private function dispatch(string $to, string $subject, string $heading, string $body, string $url, string $cta, string $footnote, ?string $unsubscribe = null): void
    {
        try {
            $mail = (new TemplatedEmail())
                ->from(new Address($this->mailFrom, $this->mailFromName))
                ->to($to)
                ->subject(preg_replace('/[\r\n]+/', ' ', $subject))
                ->htmlTemplate('emails/transac/notify.html.twig')
                ->textTemplate('emails/transac/notify.txt.twig')
                ->context(['heading' => $heading, 'body' => $body, 'link' => $url, 'cta' => $cta, 'footnote' => $footnote, 'unsubscribe' => $unsubscribe]);
            if ($unsubscribe) {
                $mail->getHeaders()->addTextHeader('List-Unsubscribe', '<' . $unsubscribe . '>');
            }
            $mail->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
            $this->mailer->send($mail);
        } catch (\Throwable $e) {
            $this->logger->warning('Notification non envoyée : '.$e->getMessage());
        }
    }
}
