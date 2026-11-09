<?php
// Lien de deconnexion perime : retour accueil, pas de 403. Lancer : APP_ENV=test php tests/e2e/e2e_logout_csrf.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$u = (new User())->setEmail('lo' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('L')->setLastName('O'); $em->persist($u); $em->flush();
$b = new KernelBrowser($k); $b->disableReboot(); $b->loginUser($u);
$b->request('GET', '/logout?_csrf_token=perime');
t('jeton perime : redirection (pas 403)', $b->getResponse()->getStatusCode() === 302);
t('vers l\'accueil', str_ends_with((string) $b->getResponse()->headers->get('Location'), '/'));
$c = $b->request('GET', '/'); $href = $c->filter('a[href*="/logout"]');
if ($href->count()) { $b->request('GET', $href->first()->attr('href')); t('jeton valide : deconnexion (302)', $b->getResponse()->getStatusCode() === 302); }
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
