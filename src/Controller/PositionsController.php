<?php

namespace App\Controller;

use App\Entity\GroupVote;
use App\Entity\PositionStance;
use App\Entity\PositionStatement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page publique "Où se situent-ils ?". Pour chaque affirmation publiée : une synthèse neutre, ce qu'on sait de la
 * faisabilité (axes documentés ou "non documenté"), le vote des groupes de l'Assemblée sur les scrutins officiels
 * rattachés, et les positions validées des personnalités. Le bloc "Mon avis" se calcule dans le navigateur
 * (localStorage) : aucune réponse du visiteur n'est envoyée au serveur.
 */
class PositionsController extends AbstractController
{
    #[Route('/positions', name: 'app_positions', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $theme = trim((string) $request->query->get('theme'));
        $all = $em->getRepository(PositionStatement::class)->findBy(['published' => true], ['id' => 'ASC']);
        $themes = array_values(array_unique(array_map(static fn (PositionStatement $s): string => $s->getTheme(), $all)));
        sort($themes);

        $blocks = [];
        $compare = [];
        foreach ($all as $s) {
            $cols = [2 => [], 1 => [], 0 => [], -1 => [], -2 => []];
            foreach ($em->getRepository(PositionStance::class)->findBy(['statement' => $s, 'status' => PositionStance::APPROVED]) as $x) {
                $cols[$x->getStance()][] = $x;
            }
            foreach ($cols as &$list) {
                usort($list, static fn (PositionStance $a, PositionStance $b): int => strcmp((string) $a->getCandidat()->getName(), (string) $b->getCandidat()->getName()));
            }
            unset($list);

            $scrutins = [];
            foreach ($em->getRepository(GroupVote::class)->findBy(['statement' => $s], ['scrutin' => 'DESC', 'groupCode' => 'ASC']) as $v) {
                $scrutins[$v->getScrutin()]['url'] = $v->getScrutinUrl();
                $scrutins[$v->getScrutin()]['date'] = $v->getVotedAt();
                $scrutins[$v->getScrutin()]['votes'][] = $v;
            }

            // Données du calcul "Mon avis" : un seul signe par acteur et par affirmation (le dernier scrutin pour les groupes).
            $actors = [];
            foreach ($cols as $val => $list) {
                foreach ($list as $x) {
                    $actors['c:' . $x->getCandidat()->getName()] = $val <=> 0;
                }
            }
            $last = $scrutins ? reset($scrutins)['votes'] : [];
            foreach ($last as $v) {
                $pos = $v->getPosition();
                $actors['g:' . $v->getGroupName()] = GroupVote::FOR === $pos ? 1 : (GroupVote::AGAINST === $pos ? -1 : 0);
            }
            $compare[$s->getId()] = $actors;

            if ('' === $theme || $s->getTheme() === $theme) {
                $blocks[] = ['statement' => $s, 'cols' => $cols, 'scrutins' => $scrutins, 'hasStances' => array_sum(array_map('count', $cols)) > 0];
            }
        }

        return $this->render('positions/index.html.twig', [
            'blocks' => $blocks,
            'labels' => PositionStance::LABELS,
            'themes' => $themes,
            'theme' => $theme,
            'compare' => $compare,
            'axes' => PositionStatement::VIABILITY_AXES,
        ]);
    }
}
