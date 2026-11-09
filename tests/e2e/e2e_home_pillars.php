<?php
// Accueil : piliers groupes par type, principes, lien sur. Lancer : APP_ENV=test php tests/e2e/e2e_home_pillars.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\Mission;
$k = new App\Kernel('test', true); $k->boot();
$c = $k->getContainer()->get('test.client'); $c->disableReboot();
$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang', 'fr'));
$em = $c->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$u = uniqid();
$a = (new Mission())->setTitre("Pilier $u")->setDescription('Desc')->setType('pilier')->setBadge('Nouveau')->setLien('/debats');
$b = (new Mission())->setTitre("Action $u")->setDescription('Desc')->setType('action')->setLien('javascript:alert(1)');
foreach ([$a, $b] as $m) { $m->setIsActive(true); $em->persist($m); }
$em->flush();
t('lien javascript: refuse', $b->getLien() === null);
t('lien interne accepte', $a->getLien() === '/debats');
$c->request('GET', '/'); $h = $c->getResponse()->getContent();
t('accueil 200', $c->getResponse()->getStatusCode() === 200);
t('pilier et action affiches', str_contains($h, "Pilier $u") && str_contains($h, "Action $u"));
t('titres de groupes', str_contains($h, 'Nos piliers') && str_contains($h, 'Nos actions'));
t('pastille', str_contains($h, 'Nouveau'));
t('principes presents', str_contains($h, 'même pays') && str_contains($h, 'WikiLeaks'));
t('pas de javascript: dans la page', !str_contains($h, 'href="javascript:'));
$c->request('GET', '/missions'); $m = $c->getResponse()->getContent();
t('missions : toutes non remunerees', str_contains($m, 'ne sont pas rémunérées') && str_contains($m, 'WikiLeaks'));
$em->createQuery("DELETE FROM App\\Entity\\Mission m WHERE m.titre IN (:t)")->setParameter("t", ["Pilier $u", "Action $u"])->execute();
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
