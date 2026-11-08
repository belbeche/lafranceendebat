<?php

namespace App\Controller;

use App\Entity\Discussion;
use App\Entity\Event;
use App\Entity\Proposition;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Agenda accessible : chaque rencontre a sa conversation sur la plateforme, pour que l'échange reste centralisé et modéré. */
class AgendaController extends AbstractController
{
    #[Route('/agenda', name: 'app_agenda', methods: ['GET'])]
    public function index(Request $request, EventRepository $events, EntityManagerInterface $em): Response
    {
        $kind = (string) $request->query->get('type', '');
        $filter = static fn (array $list) => '' === $kind ? $list : array_values(array_filter($list, static fn (Event $e) => $e->getKind() === $kind));
        $threads = [];
        foreach ($em->getRepository(Discussion::class)->findBy(['status' => Proposition::STATUS_VALIDATED]) as $d) {
            if ($d->getEvent()) {
                $threads[$d->getEvent()->getId()] = $d;
            }
        }

        return $this->render('agenda/index.html.twig', [
            'upcoming' => $filter($events->findUpcoming()),
            'past' => $filter($events->findPast()),
            'kinds' => Event::allKinds(),
            'kind' => $kind,
            'threads' => $threads,
        ]);
    }

    /** Un acteur propose une rencontre : publiée après validation. */
    #[Route('/agenda/proposer', name: 'app_agenda_new', methods: ['GET', 'POST'])]
    public function propose(Request $request, EntityManagerInterface $em, \App\Service\ContentModerator $moderator, \Symfony\Component\RateLimiter\RateLimiterFactory $eventUserLimiter, \App\Service\NotificationMailer $notifier): Response
    {
        $ok = false;
        foreach (['ROLE_ACTOR', 'ROLE_ELU', 'ROLE_ASSOCIATION', 'ROLE_MODERATOR'] as $r) {
            $ok = $ok || $this->isGranted($r);
        }
        if (!$ok) {
            $this->addFlash('warning', 'Proposer une rencontre est réservé aux acteurs (élus, associations, prestataires, médiateurs). Vous pouvez demander ce droit.');

            return $this->redirectToRoute('app_role_request');
        }
        $d = ['name' => '', 'kind' => 'debat', 'date' => '', 'location' => '', 'description' => ''];
        $errors = [];
        if ($request->isMethod('POST')) {
            foreach ($d as $k => $_) {
                $d[$k] = trim((string) $request->request->get($k, ''));
            }
            $when = \DateTime::createFromFormat('Y-m-d\TH:i', $d['date']);
            if (!$this->isCsrfTokenValid('event_new', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée, réessayez.';
            }
            if (mb_strlen($d['name']) < 8 || mb_strlen($d['name']) > 200) {
                $errors[] = 'Le titre doit faire entre 8 et 200 caractères.';
            }
            if (!isset(Event::KINDS[$d['kind']])) {
                $errors[] = 'Choisissez un type de rencontre.';
            }
            if (!$when || $when < new \DateTime('now')) {
                $errors[] = 'Indiquez une date et une heure à venir.';
            }
            if (mb_strlen($d['description']) < 40 || mb_strlen($d['description']) > 2000) {
                $errors[] = 'La description doit faire entre 40 et 2000 caractères : objet, public, modalités.';
            }
            if ($moderator->analyze($d['name'] . "\n" . $d['description'] . "\n" . $d['location']) !== []) {
                $errors[] = 'Le texte contient des termes contraires à la charte de paix.';
            }
            if (!$errors && !$eventUserLimiter->create('u' . $this->getUser()->getId())->consume()->isAccepted()) {
                $errors[] = 'Limite atteinte : 3 rencontres par jour.';
            }
            if (!$errors) {
                $e = (new Event())->setName($d['name'])->setKind($d['kind'])->setDate($when)->setLocation($d['location'] ?: null)->setDescription($d['description'])->setStatus('pending')->setProposedBy($this->getUser());
                $em->persist($e);
                $em->flush();
                $notifier->eventSubmitted($e);
                $this->addFlash('success', 'Merci. Votre rencontre sera publiée à l\'agenda après validation.');

                return $this->redirectToRoute('app_agenda');
            }
        }

        return $this->render('agenda/new.html.twig', ['d' => $d, 'errors' => $errors, 'kinds' => Event::KINDS]);
    }

    /** Ouvre (ou retrouve) la conversation de la rencontre. */
    #[Route('/agenda/{id<\d+>}/echanger', name: 'app_agenda_thread', methods: ['POST'])]
    public function thread(Event $event, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('event' . $event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée, réessayez.');

            return $this->redirectToRoute('app_agenda');
        }
        $d = $em->getRepository(Discussion::class)->findOneBy(['event' => $event]);
        if (!$d) {
            $d = (new Discussion())
                ->setTitle(mb_substr('Échanges : ' . $event->getName(), 0, 160))
                ->setBody(mb_substr(trim((string) $event->getDescription()), 0, 600) . "\n\nCe fil rassemble les questions, les attentes et les comptes rendus autour de cette rencontre. Tous les échanges restent ici, sous la charte de paix : pas d'insulte, des faits, de la courtoisie.")
                ->setCategory('dialogue')
                ->setAuthor(null)
                ->setStatus(Proposition::STATUS_VALIDATED)
                ->setEvent($event);
            $em->persist($d);
            $em->flush();
        }

        return $this->redirectToRoute('app_community_show', ['id' => $d->getId()]);
    }

    /** Ajout au calendrier de l'appareil, sans compte ni service externe. */
    #[Route('/agenda/{id<\d+>}.ics', name: 'app_agenda_ics', methods: ['GET'])]
    public function ics(Event $event): Response
    {
        $start = \DateTimeImmutable::createFromInterface($event->getDate())->setTimezone(new \DateTimeZone('UTC'));
        $esc = static fn (string $s) => addcslashes(str_replace(["\r", "\n"], ['', '\\n'], $s), ',;\\');
        $body = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//La France en Debat//FR\r\nBEGIN:VEVENT\r\nUID:event-" . $event->getId() . "@lafranceendebat\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART:" . $start->format('Ymd\THis\Z') . "\r\nDTEND:" . $start->modify('+2 hours')->format('Ymd\THis\Z')
            . "\r\nSUMMARY:" . $esc((string) $event->getName()) . "\r\nLOCATION:" . $esc((string) $event->getLocation()) . "\r\nDESCRIPTION:" . $esc(mb_substr((string) $event->getDescription(), 0, 500)) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        return new Response($body, 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="rencontre-' . $event->getId() . '.ics"']);
    }
}
