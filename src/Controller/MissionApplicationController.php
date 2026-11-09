<?php

namespace App\Controller;

use App\Entity\MissionApplication;
use App\Entity\MissionPost;
use App\Entity\User;
use App\Service\ContentModerator;
use App\Service\InAppNotifier;
use App\Service\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Candidatures aux missions : adressées aux acteurs concernés, jamais à l'administrateur (sauf si aucun acteur n'existe encore). */
#[IsGranted('ROLE_USER')]
class MissionApplicationController extends AbstractController
{
    #[Route('/missions/{key<[mp]\d+>}/candidater', name: 'app_mission_apply', methods: ['GET', 'POST'])]
    public function apply(string $key, Request $request, EntityManagerInterface $em, ContentModerator $moderator, NotificationMailer $notifier, \App\Service\ActorDirectory $directory, \App\Service\Lettre\ContactApplicationMailer $contactMailer, #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'limiter.contact_apply_user')] \Symfony\Component\RateLimiter\RateLimiterFactory $contactLimiter): Response
    {
        $mission = MissionsController::missionByKey($key) ?? throw $this->createNotFoundException();
        /** @var User $me */
        $me = $this->getUser();
        $cards = $directory->forRoles($mission[2], 30, $me);
        $actors = array_map(static fn (array $c) => $c['user'], $cards);
        $fallback = !$actors;
        $recipients = $actors ?: $this->team($em, $me);
        $contactCandidates = $contactMailer->isReady() ? $em->getRepository(\App\Entity\ActorContact::class)->createQueryBuilder('c')
            ->andWhere('c.status = :s')->setParameter('s', \App\Entity\ActorContact::ACTIVE)
            ->andWhere('c.consentAt IS NOT NULL OR c.contactCount < 1')
            ->orderBy('c.consentAt', 'DESC')->addOrderBy('c.id', 'ASC')->setMaxResults(12)->getQuery()->getResult() : [];
        $chosenContacts = array_map('intval', (array) $request->request->all('contacts'));
        $noOne = !$recipients && !$contactCandidates;
        $errors = [];
        $email = trim((string) $request->request->get('email', $me->getEmail()));
        $phone = trim((string) $request->request->get('phone', $me->getPhone() ?? ''));
        $message = trim((string) $request->request->get('message', ''));
        $chosen = array_map('intval', (array) $request->request->all('to'));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mission_apply', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Indiquez une adresse e-mail valide : les acteurs vous répondront dessus.';
            }
            if (!preg_match('/^\+?[0-9 .\-()]{8,20}$/', $phone)) {
                $errors[] = 'Indiquez un numéro de téléphone valide (8 à 20 chiffres).';
            }
            $len = mb_strlen($message);
            if ($len < 40 || $len > 1500) {
                $errors[] = 'Présentez-vous et dites ce que vous apportez en 40 à 1500 caractères.';
            } elseif (array_intersect($moderator->analyze($message), [ContentModerator::REASON_INSULT, ContentModerator::REASON_THREAT])) {
                $errors[] = 'Votre texte ne respecte pas la charte de paix. Reformulez-le.';
            }
            if ($noOne) {
                $errors[] = 'Aucun acteur ni membre de l\'équipe ne peut recevoir cette candidature pour l\'instant. Réessayez bientôt.';
            }
            $targets = $fallback ? $recipients : array_values(array_filter($recipients, fn (User $u) => in_array($u->getId(), $chosen, true)));
            $contactTargets = array_values(array_filter($contactCandidates, fn ($c) => in_array($c->getId(), $chosenContacts, true)));
            if (count($contactTargets) > 3) {
                $errors[] = 'Vous pouvez écrire à 3 contacts publics au maximum par candidature.';
            }
            if ($contactTargets && !$request->request->getBoolean('share_email')) {
                $errors[] = 'Pour écrire à un contact public, acceptez que votre e-mail lui soit transmis (il vous répond directement).';
            }
            if ($fallback && $contactTargets) {
                $targets = array_values(array_filter($recipients, static fn () => false));
            }
            if (!$targets && !$contactTargets && !$noOne) {
                $errors[] = 'Choisissez au moins un acteur.';
            }
            if (!$errors) {
                $sent = 0;
                $sentContacts = 0;
                foreach ($contactTargets as $ct) {
                    if ($em->getRepository(\App\Entity\ContactApplication::class)->findOneBy(['applicant' => $me, 'contact' => $ct, 'mission' => $mission[0]])) {
                        continue;
                    }
                    if (!$contactLimiter->create('u' . $me->getId())->consume()->isAccepted()) {
                        $this->addFlash('warning', 'Limite atteinte : 6 e-mails vers des contacts publics par jour.');
                        break;
                    }
                    $em->persist(new \App\Entity\ContactApplication($me, $ct, $mission[0], $message));
                    $contactMailer->send($me, $ct, $mission[0], $message, $email);
                    $ct->markContacted();
                    if (!$ct->hasConsent()) {
                        $ct->markInvited();
                    }
                    ++$sentContacts;
                }
                $em->flush();
                foreach ($targets as $to) {
                    if ($em->getRepository(MissionApplication::class)->findOneBy(['applicant' => $me, 'recipient' => $to, 'mission' => $mission[0]])) {
                        continue;
                    }
                    $app = new MissionApplication($me, $to, $mission[0], $message, $email, $phone, $fallback);
                    $em->persist($app);
                    $em->flush();
                    $notifier->missionApplication($app);
                    ++$sent;
                }
                if ($me->getPhone() === null || '' === $me->getPhone()) {
                    $me->setPhone($phone);
                    $em->flush();
                }
                $this->addFlash($sent ? 'success' : 'warning', ($sent || $sentContacts) ? sprintf('Candidature envoyée à %d acteur(s)%s. Vous êtes notifié de leur réponse.', $sent, $sentContacts ? ' et à ' . $sentContacts . ' contact(s) public(s) par e-mail' : '') : 'Vous avez déjà candidaté auprès de ces acteurs pour cette mission.');

                return $this->redirectToRoute('app_my_applications');
            }
        }

        return $this->render('mission_application/apply.html.twig', compact('mission', 'key', 'recipients', 'cards', 'fallback', 'noOne', 'errors', 'email', 'phone', 'message', 'chosen', 'contactCandidates', 'chosenContacts'));
    }

    #[Route('/mes-candidatures', name: 'app_my_applications', methods: ['GET'])]
    public function mine(EntityManagerInterface $em): Response
    {
        return $this->render('mission_application/mine.html.twig', ['apps' => $em->getRepository(MissionApplication::class)->findBy(['applicant' => $this->getUser()], ['id' => 'DESC']), 'contactApps' => $em->getRepository(\App\Entity\ContactApplication::class)->findBy(['applicant' => $this->getUser()], ['id' => 'DESC'])]);
    }

    #[Route('/espace-acteur/candidatures', name: 'app_received_applications', methods: ['GET'])]
    public function received(EntityManagerInterface $em, \App\Service\RankingService $ranking): Response
    {
        if (!$this->isActorOrTeam() && !$em->getRepository(MissionPost::class)->findOneBy(['author' => $this->getUser()])) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('mission_application/received.html.twig', ['apps' => $em->getRepository(MissionApplication::class)->findBy(['recipient' => $this->getUser()], ['id' => 'DESC']), 'badges' => $ranking->badges()]);
    }

    #[Route('/espace-acteur/candidatures/{id<\d+>}/repondre', name: 'app_application_respond', methods: ['POST'])]
    public function respond(MissionApplication $app, Request $request, EntityManagerInterface $em, NotificationMailer $notifier): Response
    {
        if ($app->getRecipient() !== $this->getUser() || !$this->isCsrfTokenValid('app_respond' . $app->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $status = 'accept' === $request->request->get('decision') ? MissionApplication::ACCEPTED : MissionApplication::DECLINED;
        $app->respond($status, mb_substr(trim((string) $request->request->get('note', '')), 0, 600) ?: null);
        $em->flush();
        $notifier->missionApplicationAnswered($app);
        $this->addFlash('success', MissionApplication::ACCEPTED === $status ? 'Candidature acceptée. Le candidat est prévenu et peut vous écrire.' : 'Candidature déclinée, le candidat est prévenu.');

        return $this->redirectToRoute('app_received_applications');
    }

    private function isActorOrTeam(): bool
    {
        return $this->isGranted('ROLE_ACTOR') || $this->isGranted('ROLE_ELU') || $this->isGranted('ROLE_ASSOCIATION') || $this->isGranted('ROLE_JOURNALIST') || $this->isGranted('ROLE_MODERATOR');
    }

    /** @return User[] */
    private function actorsFor(EntityManagerInterface $em, array $roles, User $me): array
    {
        $qb = $em->getRepository(User::class)->createQueryBuilder('u')->andWhere('u != :me')->setParameter('me', $me);
        $or = $qb->expr()->orX();
        foreach ($roles as $i => $r) {
            $or->add("u.roles LIKE :r$i");
            $qb->setParameter("r$i", '%"' . $r . '"%');
        }

        return $qb->andWhere($or)->orderBy('u.id', 'ASC')->setMaxResults(30)->getQuery()->getResult();
    }

    /** @return User[] */
    private function team(EntityManagerInterface $em, User $me): array
    {
        return array_values(array_filter($em->getRepository(User::class)->createQueryBuilder('u')->andWhere('u.roles LIKE :a OR u.roles LIKE :m')
            ->setParameter('a', '%"ROLE_ADMIN"%')->setParameter('m', '%"ROLE_MODERATOR"%')->setMaxResults(5)->getQuery()->getResult(), fn (User $u) => $u !== $me));
    }
}
