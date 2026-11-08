<?php

namespace App\Service\Lettre;

use App\Entity\NewsletterSubscriber;
use App\Entity\User;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Abonnement a la lettre depuis un compte, sur consentement explicite (case non cochee par defaut).
 * L'adresse du compte n'est pas verifiee : on envoie le lien de confirmation (double opt-in), sauf si le compte est deja verifie.
 */
class LettreOptIn
{
    public function __construct(
        private readonly NewsletterSubscriberRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly LettreMailer $mailer,
    ) {
    }

    /** @return string 'confirmed' | 'pending' | 'already' */
    public function subscribe(User $user): string
    {
        $email = mb_strtolower(trim((string) $user->getEmail()));
        $sub = $this->repo->findOneBy(['email' => $email]);
        if (null === $sub) {
            $sub = (new NewsletterSubscriber())->setEmail($email)->setSource('compte');
            $sub->setIsActive(false);
            $this->em->persist($sub);
        }
        if (null !== $user->getFirstName() && null === $sub->getFirstName()) {
            $sub->setFirstName($user->getFirstName())->setLastName($user->getLastName());
        }
        if ($sub->isActive() && null !== $sub->getConfirmedAt() && null === $sub->getUnsubscribedAt()) {
            $this->em->flush();

            return 'already';
        }
        if ($user->isVerified()) {
            $sub->setIsActive(true)->setConfirmedAt(new \DateTimeImmutable())->setUnsubscribedAt(null);
            $this->em->flush();

            return 'confirmed';
        }
        $this->em->flush();
        try {
            $this->mailer->sendConfirmation($sub);
        } catch (\Throwable) {
            // L'echec d'envoi ne bloque jamais l'inscription au compte.
        }

        return 'pending';
    }

    public function unsubscribe(User $user): void
    {
        $sub = $this->repo->findOneBy(['email' => mb_strtolower(trim((string) $user->getEmail()))]);
        if ($sub) {
            $sub->setIsActive(false)->setUnsubscribedAt(new \DateTimeImmutable());
            $this->em->flush();
        }
    }

    public function status(User $user): string
    {
        $sub = $this->repo->findOneBy(['email' => mb_strtolower(trim((string) $user->getEmail()))]);
        if (!$sub || null !== $sub->getUnsubscribedAt()) {
            return 'none';
        }

        return $sub->isActive() && null !== $sub->getConfirmedAt() ? 'confirmed' : 'pending';
    }
}
