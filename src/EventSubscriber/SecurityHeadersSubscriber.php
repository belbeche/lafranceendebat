<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** En-tetes de securite appliques a toutes les reponses. */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    /** Nonce CSP propre a la requete (genere a la demande, partage entre Twig et les en-tetes). */
    public static function nonce(?Request $request): string
    {
        if (null === $request) {
            return '';
        }
        if (!$request->attributes->has('_csp_nonce')) {
            $request->attributes->set('_csp_nonce', rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '='));
        }

        return (string) $request->attributes->get('_csp_nonce');
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $response = $event->getResponse();
        $h = $response->headers;
        $path = $request->getPathInfo();

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        $h->set('Cross-Origin-Resource-Policy', 'same-origin');
        $h->set('X-Permitted-Cross-Domain-Policies', 'none');
        $h->set('X-DNS-Prefetch-Control', 'off');
        $h->remove('X-Powered-By');
        $h->set('Vary', 'Cookie', false); // un cache intermediaire ne doit jamais servir la page d'un visiteur anonyme a un connecte

        // Pages privees (compte, admin, connexion, formulaires) : jamais mises en cache ni par le navigateur ni par un proxy.
        $private = $request->hasSession() && $request->getSession()->isStarted()
            || $request->cookies->has(session_name()) || $request->cookies->has('REMEMBERME')
            || preg_match('~^/(admin|profil|login|inscription|reset-password|propositions/nouvelle|communaute/nouveau|lettre)~', $path);
        if ($private && !$h->hasCacheControlDirective('no-store')) {
            $h->set('Cache-Control', 'no-store, private');
            $h->set('Pragma', 'no-cache');
        } elseif (str_contains((string) $h->get('Content-Type'), 'text/html') && !$h->hasCacheControlDirective('max-age') && !$h->hasCacheControlDirective('s-maxage')) {
            // Pages publiques : le navigateur doit revalider, sinon apres connexion il peut reafficher la version "deconnecte" gardee en memoire.
            $h->set('Cache-Control', 'no-cache, private');
        }

        // Les outils de debogage de Symfony (/_wdt, /_profiler) utilisent des scripts en ligne : hors production seulement.
        if (!str_starts_with($path, '/_')) {
            $h->set('Content-Security-Policy', str_starts_with($path, '/admin') ? $this->adminPolicy() : $this->publicPolicy($request));
        }
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }
    }

    private function publicPolicy(Request $request): string
    {
        $nonce = self::nonce($request);

        return implode('; ', [
            "default-src 'none'",
            "script-src 'self' 'nonce-$nonce' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "font-src 'self' https://cdn.jsdelivr.net data:",
            "img-src 'self' data:",
            "connect-src 'self' https://cdn.jsdelivr.net",
            "manifest-src 'self'",
            "worker-src 'self'",
            // Le paiement (contribution, commande de service) POSTe sur le site puis redirige vers Stripe Checkout :
            // Chrome applique form-action aussi aux redirections, donc Stripe doit être autorisé ici, et seulement lui.
            "form-action 'self' https://checkout.stripe.com",
            "frame-ancestors 'none'",
            "base-uri 'none'",
            "object-src 'none'",
            ...(str_starts_with($request->getPathInfo(), '/dossiers/petition') ? ["frame-src https://petitions.assemblee-nationale.fr"] : []),
            'upgrade-insecure-requests',
        ]);
    }

    /** L'administration herite d'anciens ecrans avec scripts en ligne : politique plus souple, mais reservee aux administrateurs. */
    private function adminPolicy(): string
    {
        return implode('; ', [
            "default-src 'none'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "font-src 'self' https://cdn.jsdelivr.net data:",
            "img-src 'self' data:",
            "connect-src 'self' https://cdn.jsdelivr.net",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'none'",
            "object-src 'none'",
        ]);
    }
}
