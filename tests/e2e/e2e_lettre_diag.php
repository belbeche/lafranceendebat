<?php
// Diagnostic "0 destinataire" : chaque etape de filtrage est comptee.
// Lancer : APP_ENV=test MAIL_FROM=no-reply@lafranceendebat.fr php tests/e2e/e2e_lettre_diag.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\{ActorContact, Lettre};
$k = new App\Kernel('test', true); $k->boot();
$c = $k->getContainer()->get('test.service_container');
$em = $c->get('doctrine')->getManager();
$repo = $em->getRepository(Lettre::class);
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$l = (new Lettre())->setAudience(Lettre::AUD_INVITATION)->setTarget('commune', '99');
$d = $repo->diagnose($l);
t('diagnostic renvoie des etapes', count($d) >= 4);
t('effectifs decroissants', $d[count($d) - 1]['count'] <= $d[0]['count']);
t('dernier = countFor', $d[count($d) - 1]['count'] === $repo->countFor($l));
$s = $repo->diagnose(new Lettre());
t('abonnes : 2 etapes', count($s) === 2);
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
