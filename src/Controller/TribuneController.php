<?php

namespace App\Controller;

use App\Entity\Tribune;
use App\Entity\User;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

/** Espace des journalistes indépendants : même logique que les autres droits (preuve, validation, relecture). */
class TribuneController extends AbstractController
{
    #[Route('/journalistes', name: 'app_tribunes', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $tribunes = $em->getRepository(Tribune::class)->findBy(['status' => Tribune::STATUS_PUBLISHED], ['publishedAt' => 'DESC'], 30);

        return $this->render('tribune/index.html.twig', ['tribunes' => $tribunes]);
    }

    #[Route('/journalistes/ecrire', name: 'app_tribune_new', methods: ['GET', 'POST'], priority: 10)]
    #[IsGranted('ROLE_JOURNALIST')]
    public function write(Request $request, EntityManagerInterface $em, ContentModerator $moderator, SluggerInterface $slugger, #[Autowire(service: 'limiter.tribune_user')] RateLimiterFactory $limiter, \App\Service\NotificationMailer $notifier): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $f = ['title' => '', 'lead' => '', 'body' => '', 'sources' => ''];
        $errors = [];
        if ($request->isMethod('POST')) {
            foreach ($f as $k => $_) {
                $f[$k] = trim((string) $request->request->get($k, ''));
            }
            if (!$this->isCsrfTokenValid('tribune', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            if (mb_strlen($f['title']) < 8 || mb_strlen($f['title']) > 160) {
                $errors[] = 'Le titre doit faire 8 à 160 caractères.';
            }
            if (mb_strlen($f['lead']) < 30 || mb_strlen($f['lead']) > 300) {
                $errors[] = 'Le chapeau (résumé) doit faire 30 à 300 caractères.';
            }
            if (mb_strlen($f['body']) < 400 || mb_strlen($f['body']) > 20000) {
                $errors[] = 'Le texte doit faire 400 à 20 000 caractères.';
            }
            $urls = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', $f['sources']) ?: []))));
            $bad = array_filter($urls, static fn (string $u) => !preg_match('~^https://[^\s/]+\.[^\s/]+\S*$~i', $u) || mb_strlen($u) > 255);
            if (!$urls || $bad || count($urls) > 15) {
                $errors[] = 'Indiquez 1 à 15 sources publiques en https, une par ligne.';
            }
            if (!$errors && array_intersect($moderator->analyze($f['title'] . "\n" . $f['lead'] . "\n" . $f['body']), [ContentModerator::REASON_INSULT, ContentModerator::REASON_THREAT, ContentModerator::REASON_PERSONAL_DATA])) {
                $errors[] = 'Le texte contient une insulte, une menace ou une donnée personnelle : la charte de paix l\'interdit. Reformulez.';
            }
            if (!$errors && !$limiter->create((string) $user->getId())->consume()->isAccepted()) {
                $errors[] = 'Limite atteinte : 3 tribunes par jour.';
            }
            if (!$errors) {
                $slug = strtolower((string) $slugger->slug($f['title'])->slice(0, 150)) . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
                $em->persist($tribune = new Tribune($user, $f['title'], $slug, $f['lead'], $f['body'], implode("\n", $urls)));
                $em->flush();
                $notifier->tribuneSubmitted($tribune);
                $this->addFlash('success', 'Tribune envoyée en relecture. Vous serez notifié de la décision.');

                return $this->redirectToRoute('app_tribune_mine');
            }
        }

        return $this->render('tribune/new.html.twig', ['f' => $f, 'errors' => $errors]);
    }

    #[Route('/journalistes/mes-tribunes', name: 'app_tribune_mine', methods: ['GET'], priority: 10)]
    #[IsGranted('ROLE_JOURNALIST')]
    public function mine(EntityManagerInterface $em): Response
    {
        return $this->render('tribune/mine.html.twig', ['tribunes' => $em->getRepository(Tribune::class)->findBy(['author' => $this->getUser()], ['createdAt' => 'DESC'])]);
    }

    #[Route('/journalistes/{slug}', name: 'app_tribune_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'])]
    public function show(string $slug, EntityManagerInterface $em): Response
    {
        $t = $em->getRepository(Tribune::class)->findOneBy(['slug' => $slug, 'status' => Tribune::STATUS_PUBLISHED]) ?? throw $this->createNotFoundException();

        return $this->render('tribune/show.html.twig', ['t' => $t]);
    }
}
