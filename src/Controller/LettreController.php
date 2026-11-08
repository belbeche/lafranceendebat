<?php

namespace App\Controller;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\Lettre\LettreMailer;
use App\Service\Lettre\LettreTokens;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Lettre d'information : inscription avec double confirmation, desinscription en un clic.
 * Reponses identiques que l'adresse existe ou non (aucune enumeration d'adresses).
 */
class LettreController extends AbstractController
{
    #[Route('/lettre', name: 'app_lettre', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('lettre/index.html.twig');
    }

    #[Route('/lettre/inscription', name: 'app_lettre_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        EntityManagerInterface $em,
        NewsletterSubscriberRepository $repo,
        LettreMailer $mailer,
        ValidatorInterface $validator,
        RateLimiterFactory $lettreIpLimiter,
        RateLimiterFactory $lettreEmailLimiter,
        \App\Service\Security\DisposableEmailPolicy $emailPolicy,
    ): Response {
        if (!$this->isCsrfTokenValid('lettre_subscribe', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée, réessayez.');

            return $this->redirectToRoute('app_lettre');
        }
        // Piege a robots : ce champ est cache pour les humains.
        if ('' !== trim((string) $request->request->get('website', ''))) {
            return $this->done();
        }
        if (!$lettreIpLimiter->create($request->getClientIp() ?? 'x')->consume()->isAccepted()) {
            $this->addFlash('error', 'Trop de demandes. Réessayez plus tard.');

            return $this->redirectToRoute('app_lettre');
        }
        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        if (!$this->validEmail($email, $validator)) {
            $this->addFlash('error', 'Indiquez une adresse e-mail valide.');

            return $this->redirectToRoute('app_lettre');
        }
        if ($emailPolicy->isSuspicious($email)) {
            $this->addFlash('error', 'Cette adresse e-mail est temporaire ou jetable. Utilisez une adresse durable.');

            return $this->redirectToRoute('app_lettre');
        }
        if (!$request->request->getBoolean('consent')) {
            $this->addFlash('error', 'Cochez la case pour confirmer que vous souhaitez recevoir la lettre.');

            return $this->redirectToRoute('app_lettre');
        }
        // Une adresse ne peut pas etre bombardee de demandes de confirmation.
        if (!$lettreEmailLimiter->create(hash('sha256', $email))->consume()->isAccepted()) {
            return $this->done();
        }

        $sub = $repo->findOneBy(['email' => $email]);
        if (null === $sub) {
            $sub = (new NewsletterSubscriber())->setEmail($email)->setSource('site');
            $sub->setIsActive(false);
            $em->persist($sub);
            $em->flush();
        }
        // Deja abonnee et confirmee : on ne renvoie rien (et on ne le revele pas).
        if (!($sub->isActive() && null !== $sub->getConfirmedAt() && null === $sub->getUnsubscribedAt())) {
            try {
                $mailer->sendConfirmation($sub);
            } catch (\Throwable) {
                // L'echec d'envoi n'est jamais montre : meme reponse dans tous les cas.
            }
        }

        return $this->done();
    }

    #[Route('/lettre/confirmer/{id<\d+>}/{token}', name: 'app_lettre_confirm', methods: ['GET', 'POST'])]
    public function confirm(int $id, string $token, Request $request, NewsletterSubscriberRepository $repo, LettreTokens $tokens, EntityManagerInterface $em): Response
    {
        $sub = $repo->find($id);
        if (null === $sub || !$tokens->isValidConfirm($sub, $token)) {
            return $this->render('lettre/result.html.twig', ['ok' => false, 'message' => 'Ce lien de confirmation est invalide ou a expiré. Vous pouvez refaire une demande.'], new Response('', 400));
        }
        if ($request->isMethod('POST')) {
            $sub->setIsActive(true)->setConfirmedAt(new \DateTimeImmutable())->setUnsubscribedAt(null);
            $em->flush();

            return $this->render('lettre/result.html.twig', ['ok' => true, 'message' => 'Inscription confirmée. Merci : vous recevrez la lettre de La France en Débat. Chaque message contient un lien pour vous désinscrire en un clic.']);
        }

        return $this->render('lettre/confirm.html.twig', ['id' => $id, 'token' => $token]);
    }

    /** GET : page de confirmation. POST : desinscription (compatible List-Unsubscribe-Post, sans session). */
    #[Route('/lettre/desinscription/{id<\d+>}/{token}', name: 'app_lettre_unsubscribe', methods: ['GET', 'POST'])]
    public function unsubscribe(int $id, string $token, Request $request, NewsletterSubscriberRepository $repo, LettreTokens $tokens, EntityManagerInterface $em): Response
    {
        $sub = $repo->find($id);
        if (null === $sub || !$tokens->isValidUnsubscribe($sub, $token)) {
            return $this->render('lettre/result.html.twig', ['ok' => false, 'message' => 'Ce lien de désinscription est invalide.'], new Response('', 400));
        }
        if ($request->isMethod('POST')) {
            $sub->setIsActive(false)->setUnsubscribedAt(new \DateTimeImmutable());
            $em->flush();

            return $this->render('lettre/result.html.twig', ['ok' => true, 'message' => 'Vous êtes désinscrit(e). Vous ne recevrez plus la lettre.']);
        }

        return $this->render('lettre/unsubscribe.html.twig', ['id' => $id, 'token' => $token]);
    }

    private function done(): Response
    {
        $this->addFlash('success', "Si cette adresse est valide, un message de confirmation vient d'être envoyé. Cliquez sur le lien qu'il contient pour finaliser l'inscription.");

        return $this->redirectToRoute('app_lettre');
    }

    private function validEmail(string $email, ValidatorInterface $validator): bool
    {
        return strlen($email) <= 180 && 0 === count($validator->validate($email, new Email(mode: Email::VALIDATION_MODE_HTML5)));
    }
}
