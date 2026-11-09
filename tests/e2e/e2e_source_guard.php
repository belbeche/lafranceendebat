<?php
// Controle de source avant remplissage. Lancer : APP_ENV=test php tests/e2e/e2e_source_guard.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\User;
use App\Service\Source\{SourceGuard, SourceDrafter};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$g = new SourceGuard();
$page = fn (string $extra, string $body) => '<html><head><title>T</title><meta property="og:title" content="Un titre d&#039;article"><meta property="og:description" content="Une présentation publique"><meta property="og:site_name" content="Le Média"><meta property="article:published_time" content="2026-11-08T17:00:00Z">' . $extra . '</head><body><article>' . $body . '</article></body></html>';
$long = str_repeat('<p>Un paragraphe de texte public assez long pour passer le seuil de lecture. </p>', 20);
$r = $g->inspect('https://www.lemonde.fr/a', $page('', $long));
t('media reconnu + libre : lecture integrale', $r['ok'] && $r['mode'] === 'full' && mb_strlen($r['body']) > 400);
t('meta extraites (apostrophe decodee)', $r['meta']['title'] === "Un titre d'article" && $r['meta']['published'] === '2026-11-08' && $r['meta']['site'] === 'Le Média');
$r = $g->inspect('https://www.lexpress.fr/a', $page('<meta name="tdm-reservation" content="1">', $long));
t('tdm-reservation : metadonnees seules, texte jamais garde', $r['ok'] && $r['mode'] === 'meta' && $r['body'] === '');
$r = $g->inspect('https://www.lemonde.fr/a', $page('', '<p>La suite est réservée à nos abonnés.</p>' . $long));
t('article payant : metadonnees seules', $r['mode'] === 'meta');
$r = $g->inspect('https://www.lemonde.fr/a', $page('<script type="application/ld+json">{"isAccessibleForFree": false}</script>', $long));
t('JSON-LD isAccessibleForFree=false : metadonnees seules', $r['mode'] === 'meta');
$r = $g->inspect('https://blog-inconnu.example/a', $page('', $long));
t('domaine inconnu : refuse', !$r['ok'] && $r['mode'] === 'refused');
$r = $g->inspect('http://www.lemonde.fr/a', $page('', $long)); t('http refuse', !$r['ok']);
$r = $g->inspect('https://www.insee.fr.evil.example/a', $page('', $long)); t('faux sous-domaine refuse', !$r['ok']);
$r = $g->inspect('https://user@www.lemonde.fr/a', $page('', $long)); t('identifiants dans l\'URL refuses', !$r['ok']);
$r = $g->inspect('https://www.insee.fr/fr/statistiques', $page('', $long)); t('source officielle acceptee', $r['ok'] && str_contains($r['checks'][1]['detail'], 'officielle'));
// brouillon sans cle : minimal, sans texte genere
$d = (new SourceDrafter(new Symfony\Component\HttpClient\MockHttpClient(), new Psr\Log\NullLogger(), ''))->draft($g->inspect('https://www.lemonde.fr/a', $page('', $long)));
t('sans cle Gemini : lien de source seul, rien de genere', !$d['ai'] && $d['summary'] === '' && str_contains($d['facts'], 'https://www.lemonde.fr/a'));
// avec cle (reponse simulee) : nettoyage et themes
$mock = new Symfony\Component\HttpClient\MockHttpClient(new Symfony\Component\HttpClient\Response\MockResponse(json_encode(['candidates' => [['content' => ['parts' => [['text' => json_encode(['title' => 'Faut-il <b>réformer</b> ?', 'theme' => 'Finances publiques', 'summary' => 'Exposé neutre.', 'questions' => ['Q1 ?', 'Q2 ?'], 'to_verify' => ['Chiffre X']])]]]]]])));
$d = (new SourceDrafter($mock, new Psr\Log\NullLogger(), 'cle'))->draft($g->inspect('https://www.lexpress.fr/a', $page('<meta name="tdm-reservation" content="1">', $long)));
t('brouillon IA : html retire, theme valide', $d['ai'] && $d['title'] === 'Faut-il réformer ?' && $d['theme'] === 'Finances publiques');
t('mode meta signale dans les faits', str_contains($d['facts'], "texte intégral n'a pas été lu") && str_contains($d['facts'], 'Chiffre X'));
// route
$k = new App\Kernel('test', true); $k->boot(); $em = $k->getContainer()->get('doctrine')->getManager();
$u = (new User())->setEmail('sg' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('S')->setLastName('G'); $em->persist($u); $em->flush();
$b = new KernelBrowser($k); $b->disableReboot();
$b->request('POST', '/debats/ouvrir/pre-remplir', ['source_url' => 'https://x']); t('anonyme : redirige vers connexion', in_array($b->getResponse()->getStatusCode(), [301, 302, 401, 403], true));
$b->loginUser($u);
$b->request('POST', '/debats/ouvrir/pre-remplir', ['source_url' => 'https://blog.example/a', '_token' => 'faux']); $b->followRedirect();
t('jeton invalide : message d\'erreur', str_contains($b->getResponse()->getContent(), 'alert-danger'));
$c = $b->request('GET', '/debats/ouvrir'); $tok = $c->filter('form[action$="pre-remplir"] input[name=_token]')->attr('value');
$b->request('POST', '/debats/ouvrir/pre-remplir', ['source_url' => 'https://blog.example/a', '_token' => $tok]); $b->followRedirect();
$h = $b->getResponse()->getContent();
t('domaine inconnu : rapport de controle affiche', str_contains($h, 'Contrôle de la source') && str_contains($h, 'non admise'));
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
