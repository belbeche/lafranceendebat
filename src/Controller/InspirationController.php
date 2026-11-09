<?php

namespace App\Controller;

use App\Entity\Inspiration;
use App\Entity\User;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** "Ils ont contribué" : personnes dont l'action a inspiré l'approche, avec sources, et possibilité d'en proposer d'autres. */
class InspirationController extends AbstractController
{
    #[Route('/ils-ont-contribue', name: 'app_inspirations', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em, ContentModerator $moderator): Response
    {
        $d = ['name' => '', 'domain' => 'autre', 'summary' => '', 'source' => ''];
        $errors = [];
        if ($request->isMethod('POST')) {
            if (!$this->getUser()) {
                return $this->redirectToRoute('app_login');
            }
            foreach ($d as $k => $_) {
                $d[$k] = trim((string) $request->request->get($k, ''));
            }
            if (!$this->isCsrfTokenValid('inspiration', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            if (mb_strlen($d['name']) < 3 || mb_strlen($d['name']) > 120) {
                $errors[] = 'Le nom doit faire entre 3 et 120 caractères.';
            }
            if (!isset(Inspiration::DOMAINS[$d['domain']])) {
                $errors[] = 'Choisissez un domaine.';
            }
            if (mb_strlen($d['summary']) < 40 || mb_strlen($d['summary']) > 600) {
                $errors[] = 'Décrivez ce que cette personne a apporté en 40 à 600 caractères.';
            }
            if (!preg_match('#^https://[^\s/]+\.[^\s/]+\S*$#i', $d['source']) || mb_strlen($d['source']) > 400) {
                $errors[] = 'Indiquez le lien (https) d\'une source publique.';
            }
            if ($moderator->analyze($d['name'] . ' ' . $d['summary']) !== []) {
                $errors[] = 'Le texte contient un terme non autorisé. Reformulez calmement.';
            }
            /** @var User|null $me */
            $me = $this->getUser();
            if (!$errors && $me) {
                $recent = (int) $em->getRepository(Inspiration::class)->createQueryBuilder('i')->select('COUNT(i.id)')
                    ->andWhere('i.author = :a')->andWhere('i.createdAt > :t')->setParameter('a', $me)->setParameter('t', new \DateTimeImmutable('-1 day'))->getQuery()->getSingleScalarResult();
                if ($recent >= 3) {
                    $errors[] = 'Limite atteinte : 3 propositions par jour.';
                }
            }
            if (!$errors) {
                $em->persist(new Inspiration($d['name'], $d['domain'], $d['summary'], $d['source'], $me));
                $em->flush();
                $this->addFlash('success', 'Merci. Votre proposition sera publiée après relecture par la modération.');

                return $this->redirectToRoute('app_inspirations');
            }
        }
        $domain = (string) $request->query->get('domaine', '');
        $q = trim((string) $request->query->get('q', ''));
        $qb = $em->getRepository(Inspiration::class)->createQueryBuilder('i')->andWhere('i.status = :s')->setParameter('s', Inspiration::PUBLISHED)->orderBy('i.name', 'ASC');
        if (isset(Inspiration::DOMAINS[$domain])) {
            $qb->andWhere('i.domain = :d')->setParameter('d', $domain);
        }
        if ('' !== $q) {
            $qb->andWhere('i.name LIKE :q OR i.summary LIKE :q')->setParameter('q', '%' . addcslashes(mb_substr($q, 0, 60), '%_') . '%');
        }

        return $this->render('inspiration/index.html.twig', [
            'items' => $qb->getQuery()->getResult(),
            'q' => $q,
            'domains' => Inspiration::DOMAINS,
            'domain' => $domain,
            'd' => $d,
            'errors' => $errors,
        ]);
    }

    #[Route('/ils-ont-contribue/{id}', name: 'app_inspiration_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $i = $em->getRepository(Inspiration::class)->findOneBy(['id' => $id, 'status' => Inspiration::PUBLISHED]);
        if (!$i) {
            throw $this->createNotFoundException();
        }
        $same = $em->getRepository(Inspiration::class)->createQueryBuilder('o')->andWhere('o.status = :s')->andWhere('o.domain = :d')->andWhere('o.id != :id')
            ->setParameter('s', Inspiration::PUBLISHED)->setParameter('d', $i->getDomain())->setParameter('id', $i->getId())->orderBy('o.name')->setMaxResults(6)->getQuery()->getResult();

        return $this->render('inspiration/show.html.twig', ['i' => $i, 'same' => $same]);
    }
}
