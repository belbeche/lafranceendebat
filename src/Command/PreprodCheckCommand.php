<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Controle de pre-mise en production : n'affiche jamais de secret, seulement OK / A CORRIGER. */
#[AsCommand(name: 'app:preprod:check', description: 'Verifie que la configuration est prete pour la production.')]
class PreprodCheckCommand extends Command
{
    public function __construct(
        private Connection $db,
        #[Autowire('%kernel.environment%')] private string $env,
        #[Autowire('%kernel.debug%')] private bool $debug,
        #[Autowire('%kernel.secret%')] private string $secret,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $bad = 0;
        $check = function (string $label, bool $ok, string $fix = '') use ($io, &$bad): void {
            if ($ok) {
                $io->writeln(" <info>OK</info>          $label");
            } else {
                ++$bad;
                $io->writeln(" <error>A CORRIGER</error> $label" . ($fix ? "  ->  $fix" : ''));
            }
        };
        $env = static fn (string $k): string => (string) ($_SERVER[$k] ?? $_ENV[$k] ?? getenv($k) ?: '');

        $check('APP_ENV=prod', $this->env === 'prod', 'mettre APP_ENV=prod');
        $check('APP_DEBUG desactive', !$this->debug, 'mettre APP_DEBUG=0');
        $check('APP_SECRET robuste (32+ caracteres, pas une valeur par defaut)', strlen($this->secret) >= 32 && !preg_match('/change|secret|example|^0+$/i', $this->secret), 'generer : php -r "echo bin2hex(random_bytes(32));"');
        $dsn = $env('MAILER_DSN');
        $check('MAILER_DSN renseigne (hors null://)', $dsn !== '' && !str_starts_with($dsn, 'null:'), 'voir docs/ENVOI_MASSE_SES.md');
        $check('MAIL_FROM renseigne', filter_var($env('MAIL_FROM'), FILTER_VALIDATE_EMAIL) !== false, 'ex. lettre@lafranceendebat.fr');
        $check('PUBLISHER_EMAIL renseigne (mentions legales)', filter_var($env('PUBLISHER_EMAIL'), FILTER_VALIDATE_EMAIL) !== false, 'adresse de contact de l\'editeur');
        $check('HOST_ADDRESS renseignee (adresse de l\'hebergeur)', trim($env('HOST_ADDRESS')) !== '', 'adresse du prestataire technique');
        $check('SES_SNS_TOPIC_ARN renseigne (rebonds et plaintes)', $env('SES_SNS_TOPIC_ARN') !== '', 'voir docs/ENVOI_MASSE_SES.md');
        $check('TRUSTED_PROXIES defini si derriere un proxy/WAF', $env('TRUSTED_PROXIES') !== '', 'IP du proxy, ou REMOTE_ADDR si aucun');
        $sk = $env('STRIPE_SECRET_KEY');
        $check('Stripe : cle secrete absente (services payants coupes) ou valide, avec secret de webhook', $sk === '' || ((str_starts_with($sk, 'sk_live_') || str_starts_with($sk, 'rk_live_')) && str_starts_with($env('STRIPE_WEBHOOK_SECRET'), 'whsec_')), 'en production : cle sk_live_/rk_live_ NOUVELLE + whsec_ du webhook /webhook/stripe');
        $check('Aucun fichier de copie du .env dans le projet (".env copy", .env.bak)', !glob($this->projectDir . '/.env*[ .]{copy,bak,old,save}', GLOB_BRACE), 'supprimer ces copies : elles contiennent d\'anciens secrets');
        $check('Fichier .env non lisible depuis le web (public/ ne contient pas .env)', !is_file($this->projectDir . '/public/.env'));
        $check('Dossier var/ inscriptible', is_writable($this->projectDir . '/var'));
        try {
            $this->db->executeQuery('SELECT 1')->fetchOne();
            $check('Connexion a la base', true);
            $names = $this->db->createSchemaManager()->listTableNames();
            foreach (['lettre', 'discussion', 'discussion_reply', 'gratitude', 'consultation', 'ballot', 'role_request', 'proposition', 'vote'] as $t) {
                $check("Table $t presente (migrations appliquees)", in_array($t, $names, true), 'doctrine:migrations:diff puis migrate');
            }
            $admins = (int) $this->db->fetchOne("SELECT COUNT(*) FROM users WHERE roles LIKE '%ROLE_ADMIN%'");
            $check('Au moins un compte administrateur', $admins >= 1, 'creer un compte admin');
        } catch (\Throwable) {
            $check('Connexion a la base', false, 'verifier DATABASE_URL');
        }

        $io->newLine();
        $bad === 0 ? $io->success('Pret pour la production.') : $io->warning("$bad point(s) a corriger avant la mise en production.");

        return $bad === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
