<?php

namespace App\Controller;

use App\Entity\{ActorContact, Proposition, Revendication};
use App\Service\Departments;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages locales : /departements (liste avec compteurs) et /departements/{code} (propositions et revendications
 * rattachées à ce département, institutions référencées sur place). Seuls des contenus déjà publics sont montrés.
 */
class DepartmentController extends AbstractController
{
    #[Route('/departements', name: 'app_departments', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $props = $this->counts($em, Proposition::class, 'p', 'p.status = :s', ['s' => Proposition::STATUS_VALIDATED]);
        $revs = $this->counts($em, Revendication::class, 'p', 'p.isPublished = true', []);
        $rows = [];
        foreach (Departments::ALL as $code => $name) {
            $rows[] = ['code' => $code, 'name' => $name, 'propositions' => $props[$code] ?? 0, 'revendications' => $revs[$code] ?? 0];
        }

        return $this->render('department/index.html.twig', ['rows' => $rows]);
    }

    #[Route('/departements/{code}', name: 'app_department', methods: ['GET'], requirements: ['code' => '\d{2,3}|2A|2B'])]
    public function show(string $code, EntityManagerInterface $em): Response
    {
        $name = Departments::name($code) ?? throw $this->createNotFoundException();
        $props = $em->getRepository(Proposition::class)->findBy(['department' => $code, 'status' => Proposition::STATUS_VALIDATED], ['supportCount' => 'DESC'], 30);
        $revs = $em->getRepository(Revendication::class)->findBy(['department' => $code, 'isPublished' => true], ['id' => 'DESC'], 30);
        $like = in_array($code, ['2A', '2B'], true) ? '20%' : $code . '%';
        $inst = $em->createQuery('SELECT a.category AS c, COUNT(a.id) AS n FROM ' . ActorContact::class . ' a WHERE a.status = :s AND a.postalCode LIKE :p GROUP BY a.category ORDER BY n DESC')->setParameters(['s' => ActorContact::ACTIVE, 'p' => $like])->getArrayResult();

        return $this->render('department/show.html.twig', ['code' => $code, 'name' => $name, 'props' => $props, 'revs' => $revs, 'institutions' => $inst, 'categories' => ActorContact::CATEGORIES]);
    }

    /** @return array<string,int> */
    private function counts(EntityManagerInterface $em, string $class, string $alias, string $where, array $params): array
    {
        $out = [];
        foreach ($em->createQuery("SELECT $alias.department AS d, COUNT($alias.id) AS n FROM $class $alias WHERE $where AND $alias.department IS NOT NULL GROUP BY $alias.department")->setParameters($params)->getArrayResult() as $r) {
            $out[(string) $r['d']] = (int) $r['n'];
        }

        return $out;
    }
}
