<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DebatRepository;
use App\Repository\DiscussionRepository;
use App\Repository\EventRepository;
use App\Repository\PropositionRepository;
use App\Repository\RevendicationRepository;
use App\Repository\ServiceOfferRepository;
use App\Repository\VoteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Fil d'actualité unique : tout ce qui bouge sur la plateforme, une action par carte (soutenir, lire, répondre, candidater). */
class FeedController extends AbstractController
{
    #[Route('/fil', name: 'app_feed', methods: ['GET'])]
    public function index(PropositionRepository $propositions, RevendicationRepository $claims, DebatRepository $debats, DiscussionRepository $discussions, EventRepository $events, ServiceOfferRepository $offers, VoteRepository $votes): Response
    {
        $items = [];
        $props = $propositions->findBy(['status' => 'Validée'], ['createdAt' => 'DESC'], 12);
        foreach ($props as $p) {
            $items[] = ['kind' => 'proposition', 'label' => 'Proposition', 'at' => $p->getCreatedAt(), 'title' => $p->getTitle(), 'text' => $p->getDescription(), 'theme' => $p->getTheme(), 'entity' => $p, 'url' => $this->generateUrl('app_proposition_show', ['id' => $p->getId()]), 'cta' => 'Lire et donner mon avis'];
        }
        foreach (array_slice($claims->findPublished(), 0, 8) as $r) {
            $items[] = ['kind' => 'revendication', 'label' => 'Demande citoyenne', 'at' => $r->getRaisedAt(), 'title' => $r->getTitle(), 'text' => $r->getSummary(), 'theme' => $r->getTheme(), 'entity' => $r, 'url' => $this->generateUrl('app_revendication_show', ['slug' => $r->getSlug()]), 'cta' => 'Voir la demande'];
        }
        foreach (array_slice($debats->findPublic(), 0, 6) as $d) {
            $items[] = ['kind' => 'debat', 'label' => 'Débat', 'at' => $d->getCreatedAt(), 'title' => $d->getTitle(), 'text' => $d->getSummary(), 'theme' => $d->getTheme(), 'entity' => $d, 'url' => $this->generateUrl('app_debat_show', ['slug' => $d->getSlug()]), 'cta' => 'Participer au débat'];
        }
        foreach ($discussions->findPublic(null, 8) as $d) {
            $items[] = ['kind' => 'agora', 'label' => 'Agora', 'at' => $d->getCreatedAt(), 'title' => $d->getTitle(), 'text' => $d->getBody(), 'theme' => null, 'entity' => $d, 'url' => $this->generateUrl('app_community_show', ['id' => $d->getId()]), 'cta' => 'Répondre dans l\'Agora'];
        }
        foreach (array_slice($offers->published(), 0, 6) as $o) {
            $items[] = ['kind' => 'service', 'label' => 'Service rémunéré', 'at' => $o->getCreatedAt(), 'title' => $o->getTitle(), 'text' => $o->getDescription(), 'theme' => null, 'price' => $o->getPriceCents() / 100, 'by' => $o->getProvider()->getPublicName(), 'entity' => $o, 'url' => $this->generateUrl('app_service_show', ['id' => $o->getId()]), 'cta' => 'Voir le service'];
        }
        foreach ($events->findUpcoming(5) as $e) {
            $items[] = ['kind' => 'rencontre', 'label' => 'Rencontre', 'at' => \DateTimeImmutable::createFromInterface($e->getDate()), 'when' => $e->getDate(), 'title' => $e->getName(), 'text' => $e->getDescription(), 'theme' => null, 'entity' => null, 'url' => $this->generateUrl('app_agenda'), 'cta' => 'Voir l\'agenda'];
        }
        usort($items, static fn ($a, $b) => $b['at'] <=> $a['at']);
        $user = $this->getUser();

        return $this->render('feed/index.html.twig', ['items' => array_slice($items, 0, 40), 'supportedIds' => $votes->findSupportedIds($user instanceof User ? $user : null, $props)]);
    }
}
