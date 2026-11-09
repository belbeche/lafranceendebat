<?php

namespace App\Controller;

use App\Entity\Proposition;
use App\Service\PublicActors;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Annuaire des voies officielles et generateur de lettre : relie une proposition a des acteurs publics reels. */
class ActeursController extends AbstractController
{
    #[Route('/acteurs-publics', name: 'app_actors', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('actors/index.html.twig', ['groups' => PublicActors::grouped()]);
    }

    #[Route('/propositions/{id<\d+>}/ecrire', name: 'app_proposition_letter', methods: ['GET'])]
    public function letter(Proposition $proposition, Request $request): Response
    {
        if (!$proposition->isValidated()) {
            throw $this->createNotFoundException();
        }
        $actor = PublicActors::find((string) $request->query->get('acteur')) ?? PublicActors::all()[3];
        $name = mb_substr(trim((string) $request->query->get('nom')), 0, 80);
        $url = $this->generateUrl('app_proposition_show', ['id' => $proposition->getId()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $lines = [
            $actor['salutation'],
            '',
            'Je vous écris à propos d\'une proposition citoyenne publiée sur La France en Débat, espace de dialogue indépendant : « ' . $proposition->getTitle() . ' » (thème : ' . $proposition->getTheme() . ').',
            '',
            'Mesure proposée : ' . trim((string) $proposition->getDescription()),
        ];
        if ($proposition->getProblem()) {
            $lines[] = '';
            $lines[] = 'Problème visé : ' . trim($proposition->getProblem());
        }
        if ($proposition->getCompetentAuthority()) {
            $lines[] = '';
            $lines[] = 'Autorité qui peut décider : ' . $proposition->getCompetentAuthority();
        }
        $lines[] = '';
        $lines[] = sprintf('Cette proposition a recueilli %d soutien(s) consultatif(s) sur le site (avis du réseau, sans valeur électorale).', $proposition->getSupportCount());
        $lines[] = 'La fiche complète, avec ses sources et ses limites, est consultable ici : ' . $url;
        $lines[] = '';
        $lines[] = 'Je vous demande de bien vouloir examiner cette proposition et de m\'indiquer les suites que vous envisagez, ou les raisons qui s\'y opposeraient.';
        $lines[] = '';
        $lines[] = 'Je vous prie d\'agréer l\'expression de mes salutations respectueuses.';
        $lines[] = '';
        $lines[] = $name !== '' ? $name : '[Votre prénom et votre nom]';

        return $this->render('actors/letter.html.twig', [
            'proposition' => $proposition,
            'actor' => $actor,
            'actors' => PublicActors::all(),
            'name' => $name,
            'subject' => 'Proposition citoyenne : ' . $proposition->getTitle(),
            'letter' => implode("\n", $lines),
        ]);
    }
}
