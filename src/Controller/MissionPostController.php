<?php

namespace App\Controller;

use App\Entity\MissionApplication;
use App\Entity\MissionPost;
use App\Entity\User;
use App\Service\ContentModerator;
use App\Service\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Annonces de mission des citoyens : "j'ai besoin d'aide" (demande) ou "je propose mon aide" (offre). */
class MissionPostController extends AbstractController
{
    #[Route('/missions/annonces', name: 'app_mission_posts', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $kind = $request->query->get('type');
        $criteria = ['status' => MissionPost::PUBLISHED];
        if (\in_array($kind, [MissionPost::DEMANDE, MissionPost::OFFRE], true)) {
            $criteria['kind'] = $kind;
        }
        $mine = $this->getUser() ? $em->getRepository(MissionPost::class)->findBy(['author' => $this->getUser()], ['id' => 'DESC'], 20) : [];

        return $this->render('missions/posts.html.twig', [
            'posts' => $em->getRepository(MissionPost::class)->findBy($criteria, ['createdAt' => 'DESC'], 60),
            'kind' => $criteria['kind'] ?? null,
            'mine' => $mine,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/missions/annonces/nouvelle', name: 'app_mission_post_new', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $em, ContentModerator $moderator): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        $d = [
            'kind' => MissionPost::OFFRE === $request->query->get('type', $request->request->get('kind')) ? MissionPost::OFFRE : MissionPost::DEMANDE,
            'title' => trim((string) $request->request->get('title', '')),
            'description' => trim((string) $request->request->get('description', '')),
            'territory' => trim((string) $request->request->get('territory', '')),
            'pay' => (string) $request->request->get('pay', 'free'),
        ];
        $errors = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mission_post', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            if (mb_strlen($d['title']) < 8 || mb_strlen($d['title']) > 120) {
                $errors[] = 'Le titre doit faire entre 8 et 120 caractères.';
            }
            if (mb_strlen($d['description']) < 40 || mb_strlen($d['description']) > 1500) {
                $errors[] = 'Décrivez le besoin ou ce que vous proposez en 40 à 1500 caractères.';
            }
            if (mb_strlen($d['territory']) > 80) {
                $errors[] = 'Le territoire est limité à 80 caractères.';
            }
            if (!isset(MissionPost::PAY[$d['pay']])) {
                $errors[] = 'Choisissez si la mission est bénévole ou rémunérée.';
            }
            if (array_intersect($moderator->analyze($d['title'] . ' ' . $d['description']), [ContentModerator::REASON_INSULT, ContentModerator::REASON_THREAT])) {
                $errors[] = 'Votre texte ne respecte pas la charte de paix. Reformulez-le.';
            }
            if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+|(?:\+?\d[\s.]?){9,}/', $d['title'] . ' ' . $d['description'])) {
                $errors[] = "N'écrivez ni e-mail ni téléphone dans l'annonce : les réponses passent par la plateforme.";
            }
            $recent = (int) $em->getRepository(MissionPost::class)->createQueryBuilder('p')->select('COUNT(p.id)')
                ->andWhere('p.author = :a')->andWhere('p.createdAt > :t')->setParameter('a', $me)->setParameter('t', new \DateTimeImmutable('-1 day'))->getQuery()->getSingleScalarResult();
            if ($recent >= 3) {
                $errors[] = 'Limite atteinte : 3 annonces par jour.';
            }
            if (!$errors) {
                $em->persist(new MissionPost($me, $d['kind'], $d['title'], $d['description'], $d['territory'], $d['pay']));
                $em->flush();
                $this->addFlash('success', 'Annonce reçue. Elle sera publiée après relecture par la modération.');

                return $this->redirectToRoute('app_mission_posts');
            }
        }

        return $this->render('missions/post_new.html.twig', $d + ['errors' => $errors]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/missions/annonces/{id<\d+>}/repondre', name: 'app_mission_post_reply', methods: ['GET', 'POST'])]
    public function reply(MissionPost $post, Request $request, EntityManagerInterface $em, ContentModerator $moderator, NotificationMailer $notifier): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        if (MissionPost::PUBLISHED !== $post->getStatus()) {
            throw $this->createNotFoundException();
        }
        if ($post->getAuthor() === $me) {
            $this->addFlash('warning', 'C\'est votre propre annonce.');

            return $this->redirectToRoute('app_mission_posts');
        }
        $email = trim((string) $request->request->get('email', $me->getEmail()));
        $phone = trim((string) $request->request->get('phone', $me->getPhone() ?? ''));
        $message = trim((string) $request->request->get('message', ''));
        $errors = [];
        $label = ($post->isDemande() ? 'Je réponds à : ' : 'Je souhaite votre aide : ') . $post->getTitle();
        $label = mb_substr($label, 0, 160);
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('mission_reply' . $post->getId(), (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Indiquez une adresse e-mail valide.';
            }
            if (!preg_match('/^\+?[0-9 .\-()]{8,20}$/', $phone)) {
                $errors[] = 'Indiquez un numéro de téléphone valide (8 à 20 chiffres).';
            }
            if (mb_strlen($message) < 40 || mb_strlen($message) > 1500) {
                $errors[] = 'Votre message doit faire entre 40 et 1500 caractères.';
            } elseif (array_intersect($moderator->analyze($message), [ContentModerator::REASON_INSULT, ContentModerator::REASON_THREAT])) {
                $errors[] = 'Votre texte ne respecte pas la charte de paix. Reformulez-le.';
            }
            if ($em->getRepository(MissionApplication::class)->findOneBy(['applicant' => $me, 'recipient' => $post->getAuthor(), 'mission' => $label])) {
                $errors[] = 'Vous avez déjà répondu à cette annonce.';
            }
            if (!$errors) {
                $app = new MissionApplication($me, $post->getAuthor(), $label, $message, $email, $phone);
                $em->persist($app);
                $em->flush();
                $notifier->missionApplication($app);
                $this->addFlash('success', 'Réponse envoyée à l\'auteur de l\'annonce. Vous êtes notifié de sa décision.');

                return $this->redirectToRoute('app_my_applications');
            }
        }

        return $this->render('missions/post_reply.html.twig', compact('post', 'email', 'phone', 'message', 'errors'));
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/missions/annonces/{id<\d+>}/cloturer', name: 'app_mission_post_close', methods: ['POST'])]
    public function close(MissionPost $post, Request $request, EntityManagerInterface $em): Response
    {
        if ($post->getAuthor() !== $this->getUser() || !$this->isCsrfTokenValid('mission_close' . $post->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $post->close();
        $em->flush();
        $this->addFlash('success', 'Annonce clôturée.');

        return $this->redirectToRoute('app_mission_posts');
    }
}
