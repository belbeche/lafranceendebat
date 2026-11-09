<?php

namespace App\Command;

use App\Entity\User;
use App\Service\Account\AccountEraser;
use App\Service\TransactionalMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Conservation limitee des comptes (CNIL / RGPD) : apres N mois sans activite, avis par e-mail (garder ou supprimer) ;
 * sans reponse apres le delai de grace, suppression. Activite = derniere connexion, creation du compte, ou page visitee (journal).
 * Les comptes administrateurs et moderateurs sont exclus (suivi manuel). A planifier chaque nuit.
 */
#[AsCommand(name: 'app:comptes:inactifs', description: 'Avertit puis supprime les comptes inactifs (conservation limitée)')]
class ComptesInactifsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly TransactionalMailer $mailer, private readonly AccountEraser $eraser)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('mois', null, InputOption::VALUE_REQUIRED, 'Inactivité avant avis (mois)', '24')
            ->addOption('delai', null, InputOption::VALUE_REQUIRED, 'Délai de réponse après l\'avis (jours)', '30')
            ->addOption('simuler', null, InputOption::VALUE_NONE, 'Ne rien envoyer ni supprimer');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $months = max(6, (int) $in->getOption('mois'));
        $grace = max(7, (int) $in->getOption('delai'));
        $dry = (bool) $in->getOption('simuler');
        $now = new \DateTimeImmutable();
        $cutoff = $now->modify("-{$months} months");
        $notified = $deleted = $spared = 0;

        /** @var list<User> $users */
        $users = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.isActive = true')->andWhere('u.createdAt < :c')->setParameter('c', $cutoff)->getQuery()->getResult();
        foreach ($users as $u) {
            if (array_intersect(['ROLE_ADMIN', 'ROLE_MODERATOR'], $u->getRoles())) {
                continue;
            }
            $last = max($u->getLastLogin()?->getTimestamp() ?? 0, $u->getCreatedAt()?->getTimestamp() ?? 0, $this->lastEvent($u));
            if ($last >= $cutoff->getTimestamp()) {
                if ($u->getInactivityNoticeAt()) { // l'utilisateur est revenu : l'avis tombe
                    $u->setInactivityNoticeAt(null);
                    ++$spared;
                }
                continue;
            }
            $notice = $u->getInactivityNoticeAt();
            if (!$notice) {
                if (!$dry) {
                    $u->setInactivityNoticeAt($now);
                    $this->mailer->inactivityNotice($u, $now->modify("+{$grace} days"), $months);
                }
                ++$notified;
            } elseif ($notice <= $now->modify("-{$grace} days")) {
                if (!$dry) {
                    $email = (string) $u->getEmail();
                    $this->eraser->erase($u);
                    $this->mailer->inactivityDeleted($email);
                }
                ++$deleted;
            }
        }
        if (!$dry) {
            $this->em->flush();
        }
        $out->writeln(sprintf('%s%d avis envoyé(s), %d compte(s) supprimé(s), %d avis annulé(s) (retour de l\'utilisateur).', $dry ? '[simulation] ' : '', $notified, $deleted, $spared));

        return Command::SUCCESS;
    }

    private function lastEvent(User $u): int
    {
        $d = $this->em->createQuery('SELECT MAX(e.at) FROM App\Entity\UserEvent e WHERE e.user = :u')->setParameter('u', $u)->getSingleScalarResult();

        return $d ? (new \DateTimeImmutable((string) $d))->getTimestamp() : 0;
    }
}
