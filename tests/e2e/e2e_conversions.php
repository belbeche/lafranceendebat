<?php
// Mesure de conversion anonyme. Lancer : APP_ENV=test php tests/e2e/e2e_conversions.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\User;
use App\Service\Audience\ConversionTracker;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$db = $em->getConnection();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$db->executeStatement('DELETE FROM conversion_count');
$tr = new ConversionTracker($db);
$tr->record('inscription'); $tr->record('inscription'); $tr->record('inconnu'); $tr->record('contribution');
$day = $tr->lastDays(1)[0];
t('deux inscriptions comptées', ($day['events']['inscription'] ?? 0) === 2);
t('événement inconnu ignoré', !isset($day['events']['inconnu']));
t('une seule ligne par jour et type', (int) $db->fetchOne("SELECT COUNT(*) FROM conversion_count WHERE name = 'inscription'") === 1);
$u = (new User())->setEmail('cv' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('A')->setLastName('C'); $em->persist($u); $em->flush();
t('création de compte comptée automatiquement', (int) $db->fetchOne("SELECT total FROM conversion_count WHERE name = 'inscription'") === 3);
t('aucune donnée personnelle dans la table', !str_contains(json_encode($db->fetchAllAssociative('SELECT * FROM conversion_count')), '@'));
$adm = (new User())->setEmail('ca' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('A')->setLastName('D')->setRoles(['ROLE_ADMIN']); $em->persist($adm); $em->flush();
$b = new KernelBrowser($k); $b->disableReboot(); $b->loginUser($adm);
$b->request('GET', '/admin/conversions'); t('page admin 200', $b->getResponse()->getStatusCode() === 200);
$n = new KernelBrowser($k); $n->disableReboot(); $n->request('GET', '/admin/conversions'); t('fermée au public', in_array($n->getResponse()->getStatusCode(), [301, 302, 401, 403], true));
$db->executeStatement('DELETE FROM conversion_count');
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
