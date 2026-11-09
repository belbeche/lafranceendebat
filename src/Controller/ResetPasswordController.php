<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Contrôleur HTTP "ResetPasswordController".
 *
 * Routes :
 *  - GET /reset-password (app_forgot_password_request)
 *  - GET /reset-password/check-email (app_check_email)
 *  - GET /reset-password/reset/{token} (app_reset_password)
 */
#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private \Symfony\Component\RateLimiter\RateLimiterFactory $resetIpLimiter,
        private \Symfony\Component\RateLimiter\RateLimiterFactory $resetEmailLimiter,
        private string $mailFrom,
        private \App\Service\PasswordResetMailer $resetMailer,
    ) {
    }

    /**
     * Display & process form to request a password reset.
     */
    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // La limite porte sur l'adresse saisie, qu'un compte existe ou non : l'annoncer ne révèle rien.
            $email = mb_strtolower((string) $form->get('email')->getData());
            $ipLimit = $this->resetIpLimiter->create($request->getClientIp() ?? 'x')->consume();
            $mailLimit = $this->resetEmailLimiter->create(hash('sha256', $email))->consume();
            if (!$ipLimit->isAccepted() || !$mailLimit->isAccepted()) {
                $retry = max(($ipLimit->isAccepted() ? null : $ipLimit->getRetryAfter())?->getTimestamp() ?? 0, ($mailLimit->isAccepted() ? null : $mailLimit->getRetryAfter())?->getTimestamp() ?? 0);
                $minutes = max(1, (int) ceil(($retry - time()) / 60));
                $this->addFlash('warning', sprintf('Trop de demandes pour cette adresse. Vous pourrez en refaire une dans %d minute%s. Pensez à vérifier vos courriers indésirables.', $minutes, $minutes > 1 ? 's' : ''));

                return $this->redirectToRoute('app_forgot_password_request');
            }

            return $this->processSendingPasswordResetEmail(
                $form->get('email')->getData()
            );
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    /**
     * Confirmation page after a user has requested a password reset.
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Generate a fake token if the user does not exist or someone hit this page directly.
        // This prevents exposing whether or not a user was found with the given email address or not
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, ?string $token = null): Response
    {
        if ($token) {
            // We store the token in session and remove it from the URL, to avoid the URL being
            // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
            $this->storeTokenInSession($token);
            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('Aucun token de réinitialisation trouvé.');
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                '%s - %s',
                $this->translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                $e->getReason()
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $this->resetPasswordHelper->removeResetRequest($token);

            // Encode(hash) the plain password, and set it.
            $encodedPassword = $this->passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $user->setPassword($encodedPassword);
            $this->entityManager->flush();
            $this->notifyPasswordChanged($user);

            // The session is cleaned up after the password has been changed.
            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès !');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData): RedirectResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        $resetToken = $this->resetMailer->send($user, true);
        if (null === $resetToken) {
            // Demande trop rapprochée ou envoi impossible : même réponse neutre.
            return $this->redirectToRoute('app_check_email');
        }

        // Store the token object in session for retrieval in check-email route.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }

    /** Prévient le titulaire du compte : si ce n'était pas lui, il peut réagir. */
    private function notifyPasswordChanged(User $user): void
    {
        try {
            $this->mailer->send((new \Symfony\Component\Mime\Email())
                ->from(new Address('' !== $this->mailFrom ? $this->mailFrom : 'noreply@invalid.example', 'La France en Débat'))
                ->to($user->getEmail())
                ->subject('Votre mot de passe a été modifié | La France en Débat')
                ->text("Bonjour,\n\nLe mot de passe de votre compte La France en Débat vient d'être modifié (" . date('d/m/Y à H:i') . ").\n\nSi c'est bien vous, aucune action n'est nécessaire.\nSi ce n'est pas vous, demandez tout de suite une nouvelle réinitialisation depuis la page de connexion, puis écrivez-nous via la page Contact.\n\nLa France en Débat"));
        } catch (\Throwable) {
            // Information utile, mais elle ne doit jamais bloquer le changement.
        }
    }
}
