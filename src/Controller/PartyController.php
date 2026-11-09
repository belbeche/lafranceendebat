<?php

namespace App\Controller;

use App\Entity\PartyProfile;
use App\Entity\PartyProposal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace des formations politiques et groupes parlementaires, avec ce qu'ils ont proposé.
 *  - GET /partis          (app_parties)  : toutes les formations, présentées de la même façon, ordre alphabétique
 *  - GET /partis/{code}   (app_party)    : propositions, thèmes, chiffrages sourcés (A, B, C) et hypothèses IA à part
 *
 * Neutralité : aucun classement, aucune note, aucun commentaire sur une formation. Les chiffres sont des comptages
 * et des sommes sourcées ; les hypothèses IA, relues par la modération, ne sont jamais ajoutées aux chiffrages sourcés.
 */
class PartyController extends AbstractController
{
    #[Route('/partis', name: 'app_parties', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $rows = $em->createQuery(
            'SELECT p.groupCode AS code, MAX(p.groupName) AS name, MAX(p.groupColor) AS color, MAX(p.source) AS source, COUNT(p.id) AS n,
                    SUM(CASE WHEN p.costMillions IS NOT NULL THEN 1 ELSE 0 END) AS costed
             FROM App\Entity\PartyProposal p WHERE p.published = true GROUP BY p.groupCode ORDER BY name ASC'
        )->getArrayResult();
        $profiles = [];
        foreach ($em->getRepository(PartyProfile::class)->findAll() as $pf) {
            $profiles[$pf->getGroupCode()] = $pf;
        }

        return $this->render('party/index.html.twig', ['parties' => $rows, 'profiles' => $profiles]);
    }

    #[Route('/partis/{code}', name: 'app_party', requirements: ['code' => '[A-Za-z0-9\-]{2,12}'], methods: ['GET'])]
    public function show(string $code, EntityManagerInterface $em): Response
    {
        $repo = $em->getRepository(PartyProposal::class);
        $base = $repo->createQueryBuilder('p')->andWhere('p.published = true')->andWhere('p.groupCode = :c')->setParameter('c', $code);
        $items = (clone $base)->orderBy('p.depositedAt', 'DESC')->addOrderBy('p.id', 'DESC')->setMaxResults(200)->getQuery()->getResult();
        if (!$items) {
            throw $this->createNotFoundException();
        }
        $total = (int) (clone $base)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $themes = (clone $base)->select('p.theme AS theme, COUNT(p.id) AS n')->andWhere('p.theme IS NOT NULL')->groupBy('p.theme')->orderBy('n', 'DESC')->getQuery()->getArrayResult();
        $byLevel = ['A' => ['n' => 0, 'sum' => 0], 'B' => ['n' => 0, 'sum' => 0], 'C' => ['n' => 0, 'sum' => 0]];
        $ai = ['n' => 0, 'low' => 0, 'central' => 0, 'high' => 0];
        foreach ((clone $base)->getQuery()->getResult() as $p) {
            if ($p->isCosted()) {
                ++$byLevel[$p->getCostCredibility()]['n'];
                $byLevel[$p->getCostCredibility()]['sum'] += (int) $p->getCostMillions();
            } elseif ($p->isAiPublic() && $p->hasAiEstimate()) {
                ++$ai['n'];
                $ai['low'] += (int) $p->getAiLowMillions();
                $ai['central'] += (int) $p->getAiCentralMillions();
                $ai['high'] += (int) $p->getAiHighMillions();
            }
        }

        return $this->render('party/show.html.twig', [
            'p0' => $items[0], 'items' => $items, 'total' => $total, 'themes' => $themes, 'byLevel' => $byLevel, 'ai' => $ai,
            'profile' => $em->getRepository(PartyProfile::class)->findOneBy(['groupCode' => $code]),
        ]);
    }
}
