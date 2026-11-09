<?php
// Opposition a la mesure d'audience : cookie, Sec-GPC, DNT, reactivation. Lancer : APP_ENV=test php tests/e2e/e2e_audience_optout.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\SiteVisitor;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$count = fn () => (int) $em->createQuery('SELECT COUNT(v.id) FROM App\Entity\SiteVisitor v')->getSingleScalarResult();
$visit = function (array $server = [], array $cookies = []) use ($k) {
    static $n = 20;
    $b = new KernelBrowser($k); $b->disableReboot();
    $b->setServerParameter('REMOTE_ADDR', '203.0.113.' . (++$n));
    $b->setServerParameter('HTTP_USER_AGENT', 'Mozilla/5.0 (X11; Linux) Firefox/130.0');
    foreach ($server as $a => $v) { $b->setServerParameter($a, $v); }
    foreach ($cookies as $a => $v) { $b->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie($a, $v)); }
    $b->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang', 'fr'));
    $b->request('GET', '/debats');
    return $b;
};
$a = $count(); $visit(); $b = $count();
t('visite normale comptee', $b === $a + 1);
$visit(['HTTP_SEC_GPC' => '1']); t('Sec-GPC : non compte', $count() === $b);
$visit(['HTTP_DNT' => '1']); t('DNT : non compte', $count() === $b);
$visit([], ['lfed_notrack' => '1']); t('cookie opposition : non compte', $count() === $b);
// IP anonymisee
$last = $em->createQuery('SELECT v FROM App\Entity\SiteVisitor v ORDER BY v.id DESC')->setMaxResults(1)->getOneOrNullResult();
t('IP tronquee (dernier octet a 0)', $last && str_ends_with($last->getIpAddress(), '.0'));
// bouton : le formulaire est dans la politique, POST pose / retire le cookie
$c = new KernelBrowser($k); $c->disableReboot();
$cr = $c->request('GET', '/politique-de-confidentialite');
t('bouton Desactiver le suivi present', str_contains($c->getResponse()->getContent(), 'Désactiver le suivi') && str_contains($c->getResponse()->getContent(), 'id="mesure-audience"'));
$tok = $cr->filter('form[action$="/confidentialite/suivi"] input[name=_token]')->attr('value');
$c->request('POST', '/confidentialite/suivi', ['_token' => $tok, 'action' => 'desactiver']);
$set = array_filter($c->getResponse()->headers->getCookies(), fn ($x) => $x->getName() === 'lfed_notrack' && $x->getValue() === '1');
t('POST desactiver depose lfed_notrack=1', (bool) $set && $c->getResponse()->isRedirect());
$c->request('POST', '/confidentialite/suivi', ['_token' => 'faux', 'action' => 'desactiver']);
t('CSRF invalide : aucun cookie', !array_filter($c->getResponse()->headers->getCookies(), fn ($x) => $x->getName() === 'lfed_notrack' && $x->getValue() === '1'));
$cr = $c->request('GET', '/politique-de-confidentialite');
$tok = $cr->filter('form[action$="/confidentialite/suivi"] input[name=_token]')->attr('value');
$c->request('POST', '/confidentialite/suivi', ['_token' => $tok, 'action' => 'reactiver']);
$gone = array_filter($c->getResponse()->headers->getCookies(), fn ($x) => $x->getName() === 'lfed_notrack' && ($x->getValue() === '' || $x->getValue() === null || $x->getExpiresTime() < time()));
t('POST reactiver retire le cookie', (bool) $gone);
$c->request('POST', '/confidentialite/suivi', ['_token' => $tok, 'action' => 'desactiver', 'back' => 'https://evil.example']);
t('redirection externe refusee', !str_contains((string) $c->getResponse()->headers->get('Location'), 'evil.example'));
$c->request('GET', '/cookie-preferences'); t('page cookies renvoie vers le bouton', str_contains($c->getResponse()->getContent(), '#opposition'));
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
