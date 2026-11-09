<?php

namespace App\Controller\Concern;

use Symfony\Component\HttpFoundation\Request;

/**
 * Redirection sûre : n'accepte qu'un chemin interne (commençant par un seul /) pour éviter qu'un lien ne renvoie vers un site tiers.
 * À utiliser pour tout paramètre « retour » ou « redirect » venant de l'utilisateur.
 */
trait SafeRedirectTrait
{
    /** Retourne le champ "_back" s'il s'agit d'un chemin interne sur, sinon la valeur de repli (evite les redirections ouvertes). */
    protected function safeBackUrl(Request $request, string $fallback): string
    {
        $back = (string) $request->request->get('_back', '');
        if ($back !== '' && $back[0] === '/' && !str_starts_with($back, '//') && !str_contains($back, '\\')) {
            return $back;
        }

        return $fallback;
    }
}
