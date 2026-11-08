<?php

namespace App\Twig;

use App\Entity\MissionApplication;
use App\Entity\Proposition;
use App\Entity\RoleRequest;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** next_step() : l'unique prochaine action utile pour la personne, selon son état réel. Chaque écran guide ainsi vers une action concrète. */
class NextStepExtension extends AbstractExtension
{
    public function __construct(private readonly Security $security, private readonly EntityManagerInterface $em, private readonly UrlGeneratorInterface $urls)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('next_step', $this->step(...))];
    }

    /** @return array{title: string, text: string, url: string, cta: string} */
    public function step(): array
    {
        $u = $this->security->getUser();
        if (!$u instanceof User) {
            return $this->s('Créez votre compte gratuit', 'Une minute pour proposer, soutenir ou déposer une demande.', 'app_register', 'Créer mon compte');
        }
        $isActor = (bool) array_intersect($u->getRoles(), ['ROLE_ACTOR', 'ROLE_ELU', 'ROLE_ASSOCIATION']);
        if ($isActor) {
            $n = $this->em->getRepository(MissionApplication::class)->count(['recipient' => $u, 'status' => MissionApplication::NEW]);
            if ($n > 0) {
                return $this->s($n . ' candidature' . ($n > 1 ? 's' : '') . ' à traiter', 'Des citoyens proposent leur aide. Répondez en un clic.', 'app_received_applications', 'Voir les candidatures');
            }
        }
        if ($this->em->getRepository(RoleRequest::class)->findOneBy(['user' => $u, 'status' => RoleRequest::STATUS_PENDING])) {
            return $this->s('Votre demande de droits est en cours', 'L\'équipe vérifie votre lien de preuve. Vous serez prévenu ici et par e-mail.', 'app_role_request', 'Voir ma demande');
        }
        if (!$this->em->getRepository(Vote::class)->count(['user' => $u])) {
            return $this->s('Soutenez une première proposition', 'Un clic suffit : votre soutien fait monter les idées utiles.', 'app_propositions', 'Voir les propositions');
        }
        if (!$this->em->getRepository(Proposition::class)->count(['author' => $u])) {
            return $this->s('Proposez votre idée', 'Dites ce qui devrait changer, avec une source publique.', 'app_proposition_new', 'Écrire une proposition');
        }
        if ($isActor) {
            return $this->s('Donnez un avis motivé', 'Approuvez, réservez ou refusez une proposition en expliquant pourquoi.', 'app_propositions', 'Choisir une proposition');
        }

        return $this->s('Aidez sur une mission', 'Des acteurs cherchent des bénévoles et des prestataires.', 'app_missions', 'Voir les missions');
    }

    private function s(string $title, string $text, string $route, string $cta): array
    {
        return ['title' => $title, 'text' => $text, 'url' => $this->urls->generate($route), 'cta' => $cta];
    }
}
