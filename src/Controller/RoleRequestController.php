<?php

namespace App\Controller;

use App\Entity\RoleRequest;
use App\Entity\User;
use App\Repository\RoleRequestRepository;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Candidature a un droit (ex. mediateur). Approbation par un administrateur. */
#[IsGranted('ROLE_USER')]
class RoleRequestController extends AbstractController
{
    private const RETRY_AFTER_REFUSAL_DAYS = 30;

    /** Ce que le droit permet concrètement, ce qu'il faut prouver, ce qui est vérifié. */
    private const PERKS = [
        'ROLE_MODERATOR' => ['can' => ['Relire les propositions, débats et demandes en attente', 'Publier ou refuser avec un motif écrit', 'Apaiser un échange avant qu\'il ne dégénère'], 'proof' => 'Aucun justificatif. Une relecture d\'essai peut vous être confiée.', 'check' => 'Impartialité, ton respectueux, disponibilité régulière.'],
        'ROLE_ACTOR' => ['can' => ['Publier des services avec votre propre prix', 'Recevoir des commandes et des candidatures aux missions', 'Écrire aux citoyens et aux autres acteurs', 'Donner un avis motivé sur les propositions'], 'proof' => 'Lien public sur votre activité : site professionnel, fiche SIRENE/Annuaire des entreprises, profil d\'ordre ou de réseau.', 'check' => 'Existence de la structure, cohérence avec votre demande.'],
        'ROLE_ELU' => ['can' => ['Répondre aux propositions et aux demandes de votre territoire', 'Donner un avis motivé (approuver, réserver, refuser)', 'Proposer des rencontres dans l\'agenda', 'Recevoir les candidatures de citoyens aux missions'], 'proof' => 'Lien officiel prouvant le mandat : site de la collectivité, du Parlement, annuaire des élus.', 'check' => 'Mandat en cours et nom identique à votre compte.'],
        'ROLE_ASSOCIATION' => ['can' => ['Porter des initiatives et proposer des rencontres', 'Recruter des bénévoles via les candidatures aux missions', 'Écrire aux élus et aux autres acteurs', 'Donner un avis motivé sur les propositions'], 'proof' => 'Lien public d\'existence : fiche du Journal officiel des associations, site de la structure.', 'check' => 'Existence légale et objet compatible avec la charte de paix.'],
        'ROLE_JOURNALIST' => ['can' => ['Publier des tribunes signées sur l\'espace journalistes', 'Recevoir les réactions des citoyens et des acteurs', 'Être relu par un médiateur, avec motif écrit en cas de refus'], 'proof' => 'Lien public prouvant votre activité : carte de presse, portfolio, page média ou profil professionnel.', 'check' => 'Activité réelle, signature de vos textes, respect de la charte de paix.'],
    ];

    #[Route('/candidater', name: 'app_role_request', methods: ['GET', 'POST'])]
    public function apply(Request $request, RoleRequestRepository $repo, ContentModerator $moderator, EntityManagerInterface $em, \App\Service\NotificationMailer $notifier): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $role = (string) $request->request->get('role', $request->query->get('role', array_key_first(RoleRequest::ROLES)));
        $org = mb_substr(trim((string) $request->query->get('org', '')), 0, 120);
        $motivation = trim((string) $request->request->get('motivation', $org ? "Organisation : $org.\n" : ''));
        $organisation = mb_substr(trim((string) $request->request->get('organisation', $org)), 0, 120);
        $proofUrl = trim((string) $request->request->get('proof_url', ''));
        $territory = mb_substr(trim((string) $request->request->get('territory', '')), 0, 120);
        $phone = trim((string) $request->request->get('phone', $user->getPhone() ?? ''));
        $errors = [];

        $blocked = null;

        if ($request->isMethod('POST') && !$blocked) {
            if (!$this->isCsrfTokenValid('role_request', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            } elseif (!isset(RoleRequest::ROLES[$role])) {
                $errors[] = 'Droit inconnu.';
            } elseif (in_array($role, $user->getRoles(), true)) {
                $errors[] = 'Vous disposez déjà de ce droit.';
            } elseif ($repo->hasPending($user, $role)) {
                $errors[] = 'Votre candidature est déjà en cours d\'examen.';
            } else {
                $last = $repo->lastRejection($user, $role);
                if ($last && $last->getDecidedAt() > new \DateTimeImmutable('-' . self::RETRY_AFTER_REFUSAL_DAYS . ' days')) {
                    $errors[] = sprintf('Une candidature refusée peut être renouvelée après %d jours.', self::RETRY_AFTER_REFUSAL_DAYS);
                }
            }
            if (!$errors && 'ROLE_MODERATOR' !== $role) {
                if (mb_strlen($organisation) < 2) {
                    $errors[] = 'Indiquez votre organisation ou structure.';
                }
                if (!preg_match('~^https://[^\s/]+\.[^\s/]+/?\S*$~i', $proofUrl) || mb_strlen($proofUrl) > 255) {
                    $errors[] = 'Donnez un lien public en https qui prouve votre fonction (site officiel, annuaire, registre).';
                }
            }
            if (!$errors && !preg_match('/^\+?[0-9 .\-()]{8,20}$/', $phone)) {
                $errors[] = 'Indiquez un numéro de téléphone valide : l\'équipe peut vous appeler pour vérifier.';
            }
            if (!$errors) {
                $len = mb_strlen($motivation);
                if ($len < 80 || $len > 1500) {
                    $errors[] = 'Expliquez votre motivation en 80 à 1500 caractères.';
                } elseif (array_intersect($moderator->analyze($motivation), [ContentModerator::REASON_INSULT, ContentModerator::REASON_THREAT])) {
                    $errors[] = 'Votre texte ne respecte pas la charte de paix. Reformulez-le.';
                }
            }
            if (!$errors) {
                $rr = (new RoleRequest($user, $role, $motivation))->setDetails($organisation, 'ROLE_MODERATOR' === $role ? null : $proofUrl, $territory);
                if (!$user->getPhone()) {
                    $user->setPhone($phone);
                }
                $em->persist($rr);
                $em->flush();
                $notifier->roleRequestReceived($rr);
                $this->addFlash('success', 'Candidature envoyée. Un administrateur l\'examinera.');

                return $this->redirectToRoute('app_role_request');
            }
        }

        return $this->render('role_request/apply.html.twig', [
            'roles' => RoleRequest::ROLES, 'mine' => $repo->findByUser($user), 'errors' => $errors,
            'blocked' => $blocked, 'motivation' => $motivation, 'selected' => $role, 'organisation' => $organisation, 'proofUrl' => $proofUrl, 'territory' => $territory, 'phone' => $phone, 'perks' => self::PERKS,
        ]);
    }
}
