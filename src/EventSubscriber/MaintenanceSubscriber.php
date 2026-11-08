<?php

namespace App\EventSubscriber;

use App\Service\MaintenanceMode;
use Doctrine\DBAL\Exception\ConnectionException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * Maintenance planifiée et panne de base : le visiteur voit une page claire (503 + Retry-After) au lieu d'une erreur brute.
 * Reste ouvert pendant la maintenance : connexion, administration de la maintenance, état de santé, webhooks e-mail.
 */
class MaintenanceSubscriber implements EventSubscriberInterface
{
    private const OPEN = '#^/(login|logout|sante|admin/maintenance|webhook/|css/|js/|vendor/|build/|favicon)#';

    public function __construct(private readonly MaintenanceMode $maintenance, private readonly TokenStorageInterface $tokens, private readonly Environment $twig, private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 5], KernelEvents::EXCEPTION => ['onException', 10]]; // 5 : apres le pare-feu (8)
    }

    public function onRequest(RequestEvent $e): void
    {
        if (!$e->isMainRequest() || !($state = $this->maintenance->read())) {
            return;
        }
        $request = $e->getRequest();
        if (preg_match(self::OPEN, $request->getPathInfo())) {
            return;
        }
        $user = $this->tokens->getToken()?->getUser();
        if (\is_object($user) && method_exists($user, 'getRoles') && \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return; // l'administrateur teste le site pendant la maintenance
        }
        $e->setResponse($this->page($state['message'] ?: null, $state['until'], 'maintenance'));
    }

    public function onException(ExceptionEvent $e): void
    {
        $t = $e->getThrowable();
        for ($c = $t; $c; $c = $c->getPrevious()) {
            if ($c instanceof ConnectionException || ($c instanceof \PDOException && str_contains($c->getMessage(), 'SQLSTATE[HY000] [20'))) {
                $this->logger->critical('Base de données injoignable : bascule sur la page de maintenance.', ['exception' => $t]);
                $e->setResponse($this->page(null, null, 'panne'));

                return;
            }
        }
    }

    private function page(?string $message, ?int $until, string $cause): Response
    {
        try {
            $html = $this->twig->render('maintenance/page.html.twig', ['message' => $message, 'until' => $until, 'cause' => $cause]);
        } catch (\Throwable) {
            $html = '<!doctype html><meta charset="utf-8"><title>Maintenance</title><p>Le site est momentanément indisponible. Réessayez dans quelques minutes.</p>';
        }

        return new Response($html, Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '300', 'Cache-Control' => 'no-store', 'Content-Type' => 'text/html; charset=UTF-8', 'X-Robots-Tag' => 'noindex']);
    }
}
