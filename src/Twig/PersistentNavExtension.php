<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Garde le menu latéral visible quand on parcourt l'espace utilisateur, l'espace acteur ou l'administration,
 * même sur les pages qui n'ont pas leur propre habillage.
 */
class PersistentNavExtension extends AbstractExtension
{
    /** préfixe de route => clé du lien actif dans le menu citoyen */
    private const CITIZEN = [
        'app_my_propositions' => 'props', 'app_community' => 'debats', 'app_mission_post' => 'missions', 'app_my_applications' => 'missions',
        'app_my_orders' => 'orders', 'app_order_' => 'orders', 'app_inbox' => 'inbox', 'app_notifications' => 'notif',
        'app_profile' => 'profile', 'app_change_password' => 'profile', 'app_notif_prefs' => 'profile', 'app_logout_others' => 'profile', 'app_login_history' => 'logins',
    ];
    private const ACTOR = ['app_actor_offer' => 'home', 'app_actor_onboard' => 'home'];

    public function __construct(private readonly RequestStack $requests, private readonly AuthorizationCheckerInterface $auth)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('persistent_nav', $this->context(...))];
    }

    /** @return array{kind:string,mode:string,active:string}|null */
    public function context(): ?array
    {
        $route = (string) $this->requests->getMainRequest()?->attributes->get('_route', '');
        if ('' === $route) {
            return null;
        }
        if (str_starts_with($route, 'admin_') || str_contains($route, 'blocked_ip')) {
            return $this->auth->isGranted('ROLE_MODERATOR') ? ['kind' => 'admin', 'mode' => 'admin', 'active' => ''] : null;
        }
        if (!$this->auth->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return null;
        }
        foreach (self::ACTOR as $prefix => $active) {
            if (str_starts_with($route, $prefix)) {
                return ['kind' => 'space', 'mode' => 'actor', 'active' => $active];
            }
        }
        foreach (self::CITIZEN as $prefix => $active) {
            if (str_starts_with($route, $prefix)) {
                return ['kind' => 'space', 'mode' => 'citizen', 'active' => $active];
            }
        }

        return null;
    }
}
