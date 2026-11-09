<?php
// Participation anonyme + modele de lettre par categorie.
// Lancer : APP_ENV=test MAIL_FROM=no-reply@lafranceendebat.fr php tests/e2e/e2e_anonymous.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\AnonymousContribution;
use App\Entity\ActorContact;
use App\Service\Lettre\ActorMessageTemplates;
$k = new App\Kernel('test', true); $k->boot();
$c = $k->getContainer()->get('test.client'); $c->disableReboot();
$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang', 'fr'));
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$c->request('GET', '/participer-anonymement?categorie=commune');
t('formulaire 200', $c->getResponse()->getStatusCode() === 200);
t('categorie preselectionnee', str_contains($c->getResponse()->getContent(), 'value="commune" selected'));
$tok = $c->getCrawler()->filter('main input[name=_token]')->attr('value');
$u = uniqid();
$base = ['_token' => $tok, 'category' => 'commune', 'subject' => 'Cantine scolaire ' . $u, 'message' => 'Le prix de la cantine a augmente de 20 pour cent et plusieurs familles hesitent a inscrire leurs enfants.', 'source' => 'https://example.org/doc', 'contact' => ''];
$c->request('POST', '/participer-anonymement', $base);
t('depot valide redirige', $c->getResponse()->isRedirect('/participer-anonymement/merci'));
$em = $c->getContainer()->get('doctrine')->getManager(); $em->clear();
$row = $em->getRepository(AnonymousContribution::class)->findOneBy(['subject' => 'Cantine scolaire ' . $u]);
t('enregistre en statut nouveau', $row && $row->getStatus() === 'nouveau');
t('aucune colonne IP/e-mail', !array_intersect(['ip', 'email', 'user'], array_keys($em->getClassMetadata(AnonymousContribution::class)->fieldMappings)));
$c->request('GET', '/participer-anonymement'); $tok = $c->getCrawler()->filter('main input[name=_token]')->attr('value');
$c->request('POST', '/participer-anonymement', ['_token' => $tok] + array_merge($base, ['subject' => 'Titre ok ' . $u, 'message' => 'Appelez-moi au 06 12 34 56 78 pour discuter de ce sujet important svp merci.']));
t('donnee personnelle refusee', $c->getResponse()->getStatusCode() === 422);
$c->request('GET', '/participer-anonymement'); $tok = $c->getCrawler()->filter('main input[name=_token]')->attr('value');
$c->request('POST', '/participer-anonymement', ['_token' => $tok] + array_merge($base, ['subject' => 'Robot ' . $u, 'website' => 'http://spam']));
$em->clear();
t('robot : rien enregistre', null === $em->getRepository(AnonymousContribution::class)->findOneBy(['subject' => 'Robot ' . $u]));
$c->request('POST', '/participer-anonymement', array_merge($base, ['_token' => 'faux', 'subject' => 'Csrf ' . $u]));
t('csrf invalide refuse', $c->getResponse()->getStatusCode() === 422);
foreach (array_keys(ActorMessageTemplates::ACCROCHES) as $cat) {
    $m = ActorMessageTemplates::modele($cat);
    $a = (new ActorContact('x@mairie.fr', 'Mairie de Test', 'https://www.service-public.fr/'))->setCategory($cat);
    $txt = ActorMessageTemplates::apply($m['body'], $a, 'https://x.test/p');
    t("modele $cat complet", !str_contains($txt, '{') && str_contains($txt, 'https://x.test/p') && str_contains($txt, 'Mairie de Test'));
}
$c->request('GET', '/admin/contributions-anonymes');
t('admin ferme au public', in_array($c->getResponse()->getStatusCode(), [301, 302, 401, 403], true));
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
