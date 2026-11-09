<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\UserEvent;
use App\Service\Security\DisposableEmailPolicy;
use App\Service\Security\UserJournal;
use App\Service\TransactionalMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Comptes existants avec une adresse jetable. Sans option : liste seulement.
 * --appliquer : 1) avertit et restreint (paiements bloques), 2) desactive apres le delai, 3) leve la restriction si l'adresse a ete corrigee.
 */
#[AsCommand(name: 'app:comptes:suspects', description: 'Liste, avertit, restreint puis désactive les comptes à adresse e-mail jetable')]
class ComptesSuspectsCommand extends Command
{
    private const REASON = 'Adresse e-mail temporaire, jetable ou suspecte';

    public function __construct(private readonly EntityManagerInterface $em, private readonly DisposableEmailPolicy $policy, private readonly UserJournal $journal, private readonly TransactionalMailer $mails)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('appliquer', null, InputOption::VALUE_NONE, 'Agir (sinon, simple liste)')->addOption('delai', null, InputOption::VALUE_REQUIRED, 'Jours avant désactivation', '7');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $apply = (bool) $in->getOption('appliquer');
        $days = max(1, (int) $in->getOption('delai'));
        $n = ['avertis' => 0, 'desactives' => 0, 'leves' => 0, 'vus' => 0];
        /** @var User $u */
        foreach ($this->em->getRepository(User::class)->findAll() as $u) {
            $bad = $this->policy->isSuspicious((string) $u->getEmail());
            if (!$bad && $u->isRestricted() && self::REASON === $u->getRestrictionReason()) {
                $out->writeln('Adresse corrigée : ' . DisposableEmailPolicy::mask((string) $u->getEmail()));
                if ($apply) {
                    $u->liftRestriction();
                    $this->journal->record(UserEvent::LIFTED, $u, null, 'Adresse e-mail corrigée');
                }
                ++$n['leves'];
                continue;
            }
            if (!$bad || in_array('ROLE_ADMIN', $u->getRoles(), true) || !$u->isActive()) {
                continue;
            }
            ++$n['vus'];
            $label = DisposableEmailPolicy::mask((string) $u->getEmail());
            if (!$u->isRestricted()) {
                $out->writeln('À avertir : ' . $label);
                if ($apply) {
                    $u->restrict(self::REASON)->markWarned();
                    $this->em->flush();
                    $this->mails->identityWarning($u, $days);
                    $this->journal->record(UserEvent::WARNING, $u, null, 'Avertissement identité réelle, délai ' . $days . ' jours');
                    $this->journal->record(UserEvent::RESTRICTED, $u, null, self::REASON);
                }
                ++$n['avertis'];
            } elseif ($u->getWarnedAt() && $u->getWarnedAt() < new \DateTimeImmutable('-' . $days . ' days')) {
                $out->writeln('À désactiver (délai dépassé) : ' . $label);
                if ($apply) {
                    $to = (string) $u->getEmail();
                    $u->setIsActive(false)->revokeSessions();
                    $this->em->flush();
                    $this->journal->record(UserEvent::DEACTIVATED, $u, null, self::REASON);
                    $this->mails->accountClosed($to, self::REASON);
                }
                ++$n['desactives'];
            }
        }
        if ($apply) {
            $this->em->flush();
        }
        $out->writeln(sprintf('%s : %d suspects, %d avertis, %d désactivés, %d restrictions levées.', $apply ? 'Appliqué' : 'Simulation', $n['vus'], $n['avertis'], $n['desactives'], $n['leves']));

        return Command::SUCCESS;
    }
}
