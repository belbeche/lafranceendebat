<?php

namespace App\Controller;

use App\Service\Languages;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « LanguageController ».
 *
 * Routes :
 *  - GET /langue/{code} (app_language)
 */
class LanguageController extends AbstractController
{
    #[Route('/langue/{code}', name: 'app_language', requirements: ['code' => '[a-z]{2,3}'], methods: ['GET'])]
    public function switch(string $code, Request $request, Languages $languages): RedirectResponse
    {
        if (!$languages->isSupported($code)) {
            throw $this->createNotFoundException();
        }

        $response = new RedirectResponse($this->safeTarget($request));
        $response->headers->setCookie(Cookie::create(Languages::COOKIE, $code, new \DateTimeImmutable('+1 year'), '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX));

        return $response;
    }

    /** Redirection uniquement vers un chemin local du site (jamais vers un domaine externe). */
    private function safeTarget(Request $request): string
    {
        $next = (string) $request->query->get('next', '');
        if ($next === '') {
            $referer = (string) $request->headers->get('referer', '');
            $parts = parse_url($referer);
            if ($referer !== '' && ($parts['host'] ?? null) === $request->getHost()) {
                $next = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
            }
        }
        if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\') || preg_match('/[\r\n]/', $next) || str_starts_with($next, '/langue/')) {
            return $this->generateUrl('app_home');
        }

        return $next;
    }
}
