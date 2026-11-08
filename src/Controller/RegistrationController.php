<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « RegistrationController ».
 *
 * Routes :
 *  - GET,POST /inscription (app_register)
 *  - GET /verifier-email/{id} (app_verify_email)
 *  - POST /verifier-email/renvoyer (app_verify_email_resend)
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
class RegistrationController extends AbstractController
{
    /** Profil choisi => droit d'acteur à demander (jamais attribué automatiquement). */
    private const ROLE_FOR = ['citoyen' => null, 'contribuable' => null, 'association' => 'ROLE_ASSOCIATION', 'entreprise' => 'ROLE_ACTOR', 'elu' => 'ROLE_ELU', 'expert' => 'ROLE_ACTOR'];

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        Security $security,
        RateLimiterFactory $registerIpLimiter,
        \App\Service\TransactionalMailer $mails,
        \App\Service\Lettre\LettreOptIn $lettreOptIn,
        \App\Service\Security\DisposableEmailPolicy $emailPolicy,
        \App\Service\Security\UserJournal $journal,
    ): Response {
        if ($this->getUser()) {
            $want = self::ROLE_FOR[(string) $request->query->get('profil', '')] ?? null;
            if ($want && !in_array($want, $this->getUser()->getRoles(), true)) {
                $this->addFlash('info', 'Vous avez déjà un compte : demandez ici le droit correspondant à votre profil.');

                return $this->redirectToRoute('app_role_request', ['role' => $want]);
            }
            $this->addFlash('info', 'Vous êtes déjà connecté avec un compte.');

            return $this->redirectToRoute('app_home');
        }
        // Piege a robots : champ cache que seul un script remplit. Reponse neutre, rien n'est cree.
        if ($request->isMethod('POST') && '' !== trim((string) $request->request->get('website', ''))) {
            $journal->record(\App\Entity\UserEvent::REGISTER_BLOCKED, null, null, 'Piège à robots (champ caché rempli)');

            return $this->redirectToRoute('app_home');
        }
        if ($request->isMethod('POST') && !$registerIpLimiter->create($request->getClientIp() ?? 'x')->consume()->isAccepted()) {
            return new Response('Trop de tentatives. Réessayez dans une heure.', Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => '3600']);
        }

        // Trois inscriptions refusees (e-mail suspect) depuis la meme adresse en 24 h : l'adresse est signalee et bloquee.
        if ($journal->countByIp(\App\Entity\UserEvent::REGISTER_BLOCKED, $request->getClientIp(), '-24 hours') >= 3) {
            return $this->render('security/register_blocked.html.twig', [], new Response(status: Response::HTTP_TOO_MANY_REQUESTS, headers: ['Retry-After' => '86400']));
        }

        $user = new User();
        $wanted = (string) $request->query->get('profil', 'citoyen');
        $wanted = array_key_exists($wanted, self::ROLE_FOR) ? $wanted : 'citoyen';
        $form = $this->createForm(RegistrationFormType::class, $user, ['profile' => $wanted]);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            $profile = (string) $form->get('profile')->getData();
            if (self::ROLE_FOR[$profile] && '' === trim((string) $form->get('organisation')->getData())) {
                $form->get('organisation')->addError(new \Symfony\Component\Form\FormError('Indiquez le nom de votre organisation.'));
            }
            $mail = (string) $form->get('email')->getData();
            if ('' !== $mail && !$emailPolicy->isSuspicious($mail) && !$emailPolicy->hasMailServer($mail)) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('Ce domaine ne reçoit pas de courrier. Vérifiez l\'adresse saisie.'));
            }
            if ('' !== $mail && $emailPolicy->isSuspicious($mail)) {
                $journal->record(\App\Entity\UserEvent::REGISTER_BLOCKED, null, \App\Service\Security\DisposableEmailPolicy::mask($mail), 'Domaine : ' . $emailPolicy->domainOf($mail));
                $n = $journal->countByIp(\App\Entity\UserEvent::REGISTER_BLOCKED, $request->getClientIp(), '-24 hours');
                if ($n >= 3) {
                    $journal->record(\App\Entity\UserEvent::IP_FLAGGED, null, null, 'Trois inscriptions refusées en 24 h');
                }
                $form->get('email')->addError(new \Symfony\Component\Form\FormError(match (true) {
                    $n <= 1 => 'Cette adresse e-mail est temporaire ou jetable. Utilisez une identité réelle et une adresse durable. Les tentatives suspectes sont enregistrées.',
                    2 === $n => 'Dernier avertissement : une nouvelle tentative avec une adresse suspecte sera signalée et bloquera l\'inscription depuis votre connexion.',
                    default => 'Inscription bloquée : trop de tentatives avec une adresse suspecte. Cette tentative a été signalée à la modération.',
                }));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $user->setCharterAcceptedAt(new \DateTimeImmutable());
            $user->setIsActive(true);
            $em->persist($user);
            $em->flush();
            $mails->welcome($user);
            $mails->verifyEmail($user);
            $journal->record(\App\Entity\UserEvent::REGISTER, $user, null, 'Profil : ' . (string) $form->get('profile')->getData());
            if ($form->get('newsletter')->getData()) {
                $lettreOptIn->subscribe($user);
            }

            $profile = (string) $form->get('profile')->getData();
            $org = trim((string) $form->get('organisation')->getData());
            $role = self::ROLE_FOR[$profile] ?? null;
            $this->addFlash('success', $role ? 'Bienvenue. Votre compte est créé. Dernière étape : demandez vos droits d\'acteur, l\'équipe les valide après vérification.' : 'Bienvenue. Votre compte est créé : vous pouvez déposer des propositions, soutenir et débattre.');

            $security->login($user, 'form_login', 'main');

            return $role ? $this->redirectToRoute('app_role_request', ['role' => $role, 'org' => $org]) : $this->redirectToRoute('app_welcome_profile', ['profile' => $profile]);
        }

        return $this->render('security/register.html.twig', ['registrationForm' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Lien signe envoye par e-mail : confirme l'adresse. */
    #[Route('/verifier-email/{id}', name: 'app_verify_email', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function verifyEmail(int $id, Request $request, EntityManagerInterface $em, \Symfony\Component\HttpFoundation\UriSigner $signer, \App\Service\Security\UserJournal $journal): Response
    {
        $user = $em->getRepository(User::class)->find($id);
        $exp = (int) $request->query->get('exp', 0);
        if (!$user || !$signer->checkRequest($request) || $exp < time()) {
            $this->addFlash('danger', 'Ce lien de confirmation est invalide ou expiré. Demandez-en un nouveau depuis votre profil.');

            return $this->redirectToRoute($this->getUser() ? 'app_profile' : 'app_login');
        }
        if (!$user->isVerified()) {
            $user->setIsVerified(true);
            $em->flush();
            $journal->record(\App\Entity\UserEvent::EMAIL_VERIFIED, $user);
        }
        $this->addFlash('success', 'Adresse e-mail confirmée. Merci !');

        return $this->redirectToRoute($this->getUser() ? 'app_profile' : 'app_login');
    }

    /** Renvoi du lien (membre connecte, une fois par heure). */
    #[Route('/verifier-email/renvoyer', name: 'app_verify_email_resend', methods: ['POST'], priority: 5)]
    public function resend(Request $request, \App\Service\TransactionalMailer $mails, \Psr\Cache\CacheItemPoolInterface $cache): Response
    {
        /** @var User|null $u */
        $u = $this->getUser();
        if ($u && $this->isCsrfTokenValid('verify_resend', (string) $request->request->get('_token')) && !$u->isVerified()) {
            $item = $cache->getItem('verif_' . $u->getId());
            if ($item->isHit()) {
                $this->addFlash('info', 'Un lien vient d\'être envoyé. Réessayez dans une heure.');
            } else {
                $cache->save($item->set(1)->expiresAfter(3600));
                $mails->verifyEmail($u);
                $this->addFlash('success', 'Lien de confirmation envoyé.');
            }
        }

        return $this->redirectToRoute($u ? 'app_profile' : 'app_login');
    }
}
