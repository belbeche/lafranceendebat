<?php

namespace App\Command;

use App\Service\Backup\DatabaseBackup;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Tâche planifiée : `app:db:backup` chaque nuit, `app:db:backup --controle` chaque matin (alerte e-mail en cas de problème). */
#[AsCommand(name: 'app:db:backup', description: 'Sauvegarde la base de données, ou contrôle l\'état des sauvegardes')]
class BackupDatabaseCommand extends Command
{
    public function __construct(
        private readonly DatabaseBackup $backup,
        private readonly MailerInterface $mailer,
        #[Autowire('%env(PUBLISHER_EMAIL)%')] private readonly string $alertTo,
        #[Autowire('%env(MAIL_FROM)%')] private readonly string $mailFrom,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('controle', 'c', InputOption::VALUE_NONE, 'Ne rien sauvegarder : vérifier et alerter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('controle')) {
            $s = $this->backup->run();
            $output->writeln($s['ok'] ? sprintf('Sauvegarde %s : %d Ko en %s s', $s['file'], intdiv($s['size'], 1024), $s['seconds']) : '<error>Échec : ' . $s['error'] . '</error>');
        }
        $problems = $this->backup->problems();
        if ($problems) {
            foreach ($problems as $p) {
                $output->writeln('<comment>' . $p . '</comment>');
            }
            $this->alert($problems);

            return Command::FAILURE;
        }
        $output->writeln('Sauvegardes : tout est en ordre.');

        return Command::SUCCESS;
    }

    private function alert(array $problems): void
    {
        if ('' === $this->alertTo || '' === $this->mailFrom) {
            return;
        }
        try {
            $this->mailer->send((new Email())->from(new Address($this->mailFrom, 'La France en Débat'))->to($this->alertTo)
                ->subject('Alerte sauvegardes | La France en Débat')
                ->text("Un problème a été détecté sur les sauvegardes de la base :\n\n- " . implode("\n- ", $problems) . "\n\nOuvrez /admin/sauvegardes pour le détail."));
        } catch (\Throwable) {
        }
    }
}
