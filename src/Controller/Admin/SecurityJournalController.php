<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\UserEvent;
use App\Service\Security\UserJournal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Journal de securite : evenements, adresses IP, pays, pages par utilisateur. Administrateurs uniquement (donnees personnelles). */
#[Route('/admin/securite')]
#[IsGranted('ROLE_ADMIN')]
class SecurityJournalController extends AbstractController
{
    #[Route('', name: 'admin_security', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $qb = $this->filtered($request, $em);
        $page = max(1, (int) $request->query->get('p', 1));
        $total = (int) (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
        $rows = $qb->orderBy('e.at', 'DESC')->setFirstResult(($page - 1) * 100)->setMaxResults(100)->getQuery()->getResult();
        $since = new \DateTimeImmutable('-7 days');
        $counts = $em->createQuery('SELECT e.type t, COUNT(e.id) n FROM App\Entity\UserEvent e WHERE e.at > :s GROUP BY e.type')->setParameter('s', $since)->getArrayResult();
        $restricted = $em->getRepository(User::class)->createQueryBuilder('u')->andWhere('u.restrictedAt IS NOT NULL')->orderBy('u.restrictedAt', 'DESC')->setMaxResults(50)->getQuery()->getResult();

        return $this->render('admin/securite/index.html.twig', [
            'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / 100)),
            'counts' => array_column($counts, 'n', 't'), 'types' => UserEvent::LABELS, 'restricted' => $restricted,
            'f' => ['type' => (string) $request->query->get('type', ''), 'ip' => trim((string) $request->query->get('ip', '')), 'q' => trim((string) $request->query->get('q', ''))],
        ]);
    }

    #[Route('/utilisateur/{id}', name: 'admin_security_user', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function user(User $u, EntityManagerInterface $em): Response
    {
        $events = $em->getRepository(UserEvent::class)->findBy(['user' => $u], ['at' => 'DESC'], 300);
        $ips = $em->createQuery('SELECT e.ip ip, e.country c, COUNT(e.id) n, MAX(e.at) last FROM App\Entity\UserEvent e WHERE e.user = :u AND e.ip IS NOT NULL GROUP BY e.ip, e.country ORDER BY n DESC')->setParameter('u', $u)->setMaxResults(30)->getArrayResult();
        $pages = $em->createQuery("SELECT e.path p, COUNT(e.id) n, MAX(e.at) last FROM App\Entity\UserEvent e WHERE e.user = :u AND e.type = 'page' AND e.path IS NOT NULL GROUP BY e.path ORDER BY n DESC")->setParameter('u', $u)->setMaxResults(30)->getArrayResult();

        return $this->render('admin/securite/user.html.twig', ['u' => $u, 'events' => $events, 'ips' => $ips, 'pages' => $pages]);
    }

    #[Route('/utilisateur/{id}/{action}', name: 'admin_security_act', requirements: ['id' => '\d+', 'action' => 'restreindre|lever|avertir'], methods: ['POST'])]
    public function act(User $u, string $action, Request $request, EntityManagerInterface $em, UserJournal $journal, \App\Service\TransactionalMailer $mails): Response
    {
        if (!$this->isCsrfTokenValid('sec' . $u->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('admin_security_user', ['id' => $u->getId()]);
        }
        if (in_array('ROLE_ADMIN', $u->getRoles(), true)) {
            $this->addFlash('danger', 'Un administrateur ne peut pas être restreint ici.');

            return $this->redirectToRoute('admin_security_user', ['id' => $u->getId()]);
        }
        $reason = trim((string) $request->request->get('reason')) ?: 'Décision de la modération';
        if ('restreindre' === $action) {
            $u->restrict($reason);
            $journal->record(UserEvent::RESTRICTED, $u, null, $reason);
            $this->addFlash('success', 'Compte restreint : paiements bloqués.');
        } elseif ('lever' === $action) {
            $u->liftRestriction();
            $journal->record(UserEvent::LIFTED, $u, null, 'Levée par ' . $this->getUser()?->getUserIdentifier());
            $this->addFlash('success', 'Restriction levée.');
        } else {
            $u->markWarned();
            $mails->identityWarning($u, 7);
            $journal->record(UserEvent::WARNING, $u, null, 'Avertissement manuel');
            $this->addFlash('success', 'Avertissement envoyé.');
        }
        $em->flush();

        return $this->redirectToRoute('admin_security_user', ['id' => $u->getId()]);
    }

    #[Route('/export.csv', name: 'admin_security_export', methods: ['GET'])]
    public function export(Request $request, EntityManagerInterface $em): Response
    {
        $rows = $this->filtered($request, $em)->orderBy('e.at', 'DESC')->setMaxResults(20000)->getQuery()->getResult();

        return new StreamedResponse(function () use ($rows) {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['Date', 'Type', 'Utilisateur', 'IP', 'Pays', 'Ville', 'Appareil', 'Page', 'Détail'], ';');
            /** @var UserEvent $e */
            foreach ($rows as $e) {
                fputcsv($h, array_map(static fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, [$e->getAt()->format('Y-m-d H:i:s'), $e->getType(), $e->getUser()?->getEmail() ?? $e->getLabel(), $e->getIp(), $e->getCountry(), $e->getCity(), $e->getDevice(), $e->getPath(), $e->getDetail()]), ';');
            }
            fclose($h);
        }, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="journal-securite.csv"']);
    }

    private function filtered(Request $request, EntityManagerInterface $em): \Doctrine\ORM\QueryBuilder
    {
        $qb = $em->getRepository(UserEvent::class)->createQueryBuilder('e')->leftJoin('e.user', 'u')->addSelect('u');
        $type = (string) $request->query->get('type', '');
        if (isset(UserEvent::LABELS[$type])) {
            $qb->andWhere('e.type = :t')->setParameter('t', $type);
        }
        if ('' !== ($ip = trim((string) $request->query->get('ip', '')))) {
            $qb->andWhere('e.ip LIKE :ip')->setParameter('ip', addcslashes(mb_substr($ip, 0, 45), '%_') . '%');
        }
        if ('' !== ($q = trim((string) $request->query->get('q', '')))) {
            $qb->andWhere('u.email LIKE :q OR e.label LIKE :q OR e.path LIKE :q')->setParameter('q', '%' . addcslashes(mb_substr($q, 0, 80), '%_') . '%');
        }

        return $qb;
    }
}
