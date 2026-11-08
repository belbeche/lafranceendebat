<?php

namespace App\Controller;

use App\Entity\ServiceOffer;
use App\Entity\User;
use App\Repository\ServiceOfferRepository;
use App\Repository\ServiceOrderRepository;
use App\Service\ContentModerator;
use App\Service\Payment\MarketplaceService;
use App\Service\Payment\PaymentGateway;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contrôleur HTTP « ActorSpaceController ».
 *
 * Routes :
 *  - GET /espace-acteur (app_actor_space)
 *  - POST /espace-acteur/paiements (app_actor_onboard)
 *  - GET /espace-acteur/paiements/retour (app_actor_onboard_return)
 *  - GET,POST /espace-acteur/offre/nouvelle (app_actor_offer_new)
 *  - POST /espace-acteur/offre/{id<\d+>}/retirer (app_actor_offer_withdraw)
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 * Tout texte public passe par ContentModerator avant d'être enregistré.
 */
#[Route('/espace-acteur')]
class ActorSpaceController extends AbstractController
{
    #[Route('', name: 'app_actor_space', methods: ['GET'])]
    public function index(ServiceOfferRepository $offers, ServiceOrderRepository $orders, MarketplaceService $market): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        /** @var User $u */
        $u = $this->getUser();

        return $this->render('marketplace/actor.html.twig', ['offers' => $offers->byProvider($u), 'orders' => $orders->forProvider($u), 'bps' => $market->commissionBps(), 'days' => ServiceOrderRepository::AUTO_RELEASE_DAYS]);
    }

    #[Route('/paiements', name: 'app_actor_onboard', methods: ['POST'])]
    public function onboard(Request $request, PaymentGateway $gateway, EntityManagerInterface $em): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        if (!$this->isCsrfTokenValid('actor_onboard', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('app_actor_space');
        }
        if (!$gateway->isConfigured()) {
            $this->addFlash('danger', 'Le paiement n\'est pas encore activé sur la plateforme.');

            return $this->redirectToRoute('app_actor_space');
        }
        /** @var User $u */
        $u = $this->getUser();
        if ($u->isPaymentBlocked()) {
            $this->addFlash('danger', $u->isRestricted() ? 'Votre compte est restreint (identité à vérifier) : l\'ouverture des paiements est bloquée. Consultez la notice « identité réelle ».' : 'Confirmez votre adresse e-mail (lien reçu à l\'inscription) avant d\'ouvrir les paiements.');

            return $this->redirectToRoute('app_actor_space');
        }
        try {
            if (!$u->getStripeAccountId()) {
                $u->setStripeAccountId($gateway->createConnectedAccount((string) $u->getEmail()));
                $em->flush();
            }
            $url = $gateway->onboardingUrl($u->getStripeAccountId(), $this->generateUrl('app_actor_onboard_return', [], UrlGeneratorInterface::ABSOLUTE_URL), $this->generateUrl('app_actor_space', [], UrlGeneratorInterface::ABSOLUTE_URL));
        } catch (\Throwable) {
            $this->addFlash('danger', 'Stripe est momentanément indisponible. Réessayez.');

            return $this->redirectToRoute('app_actor_space');
        }

        return $this->redirect($url);
    }

    #[Route('/paiements/retour', name: 'app_actor_onboard_return', methods: ['GET'])]
    public function onboardReturn(PaymentGateway $gateway, EntityManagerInterface $em): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        /** @var User $u */
        $u = $this->getUser();
        if ($u->getStripeAccountId()) {
            try {
                $st = $gateway->accountStatus($u->getStripeAccountId());
                $u->setStripeReady($st['charges_enabled'] && $st['payouts_enabled']);
                $em->flush();
                $this->addFlash($u->isStripeReady() ? 'success' : 'warning', $u->isStripeReady() ? 'Paiements activés. Vous pouvez publier des prestations.' : 'Dossier Stripe incomplet : reprenez la vérification pour être payé.');
            } catch (\Throwable) {
                $this->addFlash('danger', 'Statut Stripe indisponible, réessayez.');
            }
        }

        return $this->redirectToRoute('app_actor_space');
    }

    #[Route('/offre/nouvelle', name: 'app_actor_offer_new', methods: ['GET', 'POST'])]
    public function newOffer(Request $request, EntityManagerInterface $em, ContentModerator $moderator, MarketplaceService $market, \App\Service\PoliticalGuard $political): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        /** @var User $u */
        $u = $this->getUser();
        if (!$market->canSell($u)) {
            $this->addFlash('warning', 'Activez d\'abord vos paiements pour publier une prestation.');

            return $this->redirectToRoute('app_actor_space');
        }
        $errors = [];
        $data = ['title' => '', 'description' => '', 'category' => 'mediation', 'price' => '', 'area' => ''];
        if ($request->isMethod('POST')) {
            foreach ($data as $k => $_) {
                $data[$k] = trim((string) $request->request->get($k, ''));
            }
            if (!$this->isCsrfTokenValid('offer_new', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée, réessayez.';
            }
            $cents = (int) round(((float) str_replace(',', '.', $data['price'])) * 100);
            if (mb_strlen($data['title']) < 8 || mb_strlen($data['title']) > 120) {
                $errors[] = 'Le titre doit faire entre 8 et 120 caractères.';
            }
            if (mb_strlen($data['description']) < 80 || mb_strlen($data['description']) > 4000) {
                $errors[] = 'Décrivez précisément la prestation (80 à 4000 caractères) : contenu, durée, livrable.';
            }
            if (!isset(ServiceOffer::CATEGORIES[$data['category']])) {
                $errors[] = 'Catégorie invalide.';
            }
            if ($cents < ServiceOffer::MIN_PRICE_CENTS || $cents > ServiceOffer::MAX_PRICE_CENTS) {
                $errors[] = sprintf('Le prix doit être compris entre %d et %d euros.', ServiceOffer::MIN_PRICE_CENTS / 100, ServiceOffer::MAX_PRICE_CENTS / 100);
            }
            if (!$request->request->getBoolean('no_political')) {
                $errors[] = 'Confirmez que votre prestation n\'est pas destinée à un parti, un candidat ou une campagne.';
            }
            if ($hit = $political->find($data['title'] . ' ' . $data['description'])) {
                $errors[] = 'Votre texte évoque un usage politique (« ' . $hit . ' »). Les prestations pour un parti, un candidat ou une campagne sont interdites.';
            }
            if ($moderator->containsInsult($data['title'] . ' ' . $data['description'])) {
                $errors[] = 'Le texte contient des termes contraires à la charte de paix.';
            }
            if (!$errors) {
                $o = (new ServiceOffer($u))->setTitle($data['title'])->setDescription($data['description'])->setCategory($data['category'])->setPriceCents($cents)->setArea($data['area']);
                $em->persist($o);
                $em->flush();
                $this->addFlash('success', 'Prestation envoyée. Elle sera publiée après relecture par la modération.');

                return $this->redirectToRoute('app_actor_space');
            }
        }

        return $this->render('marketplace/offer_new.html.twig', ['errors' => $errors, 'data' => $data, 'categories' => ServiceOffer::CATEGORIES, 'bps' => $market->commissionBps()]);
    }

    #[Route('/offre/{id<\d+>}/retirer', name: 'app_actor_offer_withdraw', methods: ['POST'])]
    public function withdraw(ServiceOffer $offer, Request $request, EntityManagerInterface $em): Response
    {
        if ($offer->getProvider() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        if ($this->isCsrfTokenValid('offer_withdraw' . $offer->getId(), (string) $request->request->get('_token'))) {
            $offer->setStatus(ServiceOffer::STATUS_WITHDRAWN);
            $em->flush();
            $this->addFlash('success', 'Prestation retirée. Les commandes déjà payées restent dues.');
        }

        return $this->redirectToRoute('app_actor_space');
    }

    private function guard(): ?Response
    {
        if (!$this->getParameter('app.marketplace_open') && !$this->isGranted('ROLE_ACTOR')) {
            $this->addFlash('info', 'Cet espace est réservé aux acteurs publics et prestataires vérifiés. Déposez votre candidature.');

            return $this->redirectToRoute('app_role_request');
        }

        return null;
    }
}
