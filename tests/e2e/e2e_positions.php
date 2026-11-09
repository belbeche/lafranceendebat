<?php
// Positions des candidats sourcées. Lancer : APP_ENV=test php tests/e2e/e2e_positions.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\{Candidat, PositionStance, PositionStatement, User};
use App\Service\Positions\{StanceResearcher, PositionResearchRunner};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpClient\{MockHttpClient};
use Symfony\Component\HttpClient\Response\MockResponse;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
// parse : garde-fous
$g = ['assemblee-nationale.fr', 'lemonde.fr'];
$good = '{"found":true,"stance":-1,"summary":"A voté contre.","source_url":"https://www.assemblee-nationale.fr/dyn/17/scrutins/12"}';
t('source consultée acceptée', (StanceResearcher::parse($good, $g)['stance'] ?? null) === -1);
t('source non consultée écartée', isset(StanceResearcher::parse($good, ['lemonde.fr'])['error']));
t('sans source écartée', isset(StanceResearcher::parse('{"found":true,"stance":1,"summary":"x","source_url":""}', $g)['error']));
t('http non sécurisé écarté', isset(StanceResearcher::parse('{"found":true,"stance":1,"summary":"x","source_url":"http://lemonde.fr/a"}', $g)['error']));
t('aucune position trouvée = null', StanceResearcher::parse('{"found":false}', $g) === null);
t('stance hors bornes écartée', isset(StanceResearcher::parse('{"found":true,"stance":5,"summary":"x","source_url":"https://lemonde.fr/a"}', $g)['error']));
t('bloc de code JSON toléré', (StanceResearcher::parse("```json\n" . $good . "\n```", $g)['stance'] ?? null) === -1);
t('domaines de grounding lus', StanceResearcher::domains(['candidates' => [['groundingMetadata' => ['groundingChunks' => [['web' => ['title' => 'www.Lemonde.fr']]]]]]]) === ['lemonde.fr']);
// runner avec Gemini simulé
$payload = json_encode(['candidates' => [['content' => ['parts' => [['text' => $good]]], 'groundingMetadata' => ['groundingChunks' => [['web' => ['title' => 'assemblee-nationale.fr']]]]]]]);
$researcher = new StanceResearcher(new MockHttpClient(fn () => new MockResponse($payload)), new Psr\Log\NullLogger(), 'cle-test');
$em->createQuery('DELETE FROM App\Entity\PositionStance')->execute();
$c = (new Candidat())->setName('Personne Test ' . uniqid())->setSlug('pt-' . uniqid())->setIsPublished(true); $em->persist($c);
$s = new PositionStatement('Immigration', 'Il faut réduire l\'immigration légale en France.'); $em->persist($s); $em->flush();
$r = (new PositionResearchRunner($researcher, $em))->run($s, 500, 60);
t('position proposée enregistrée', $r['added'] >= 1);
$x = $em->getRepository(PositionStance::class)->findOneBy(['statement' => $s, 'candidat' => $c]);
t('en attente et étiquetée IA', $x && $x->getStatus() === 'en_attente' && $x->isAiProposed());
$r2 = (new PositionResearchRunner($researcher, $em))->run($s, 500, 60);
t('pas de doublon au second passage', $r2['added'] === 0);
$s->setPublished(true); $em->flush();
$b = new KernelBrowser($k); $b->disableReboot();
$b->request('GET', '/positions'); t('page publique 200', $b->getResponse()->getStatusCode() === 200);
t('position en attente invisible', !str_contains($b->getResponse()->getContent(), $c->getName()));
$adm = (new User())->setEmail('qz' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('A')->setLastName('Q')->setRoles(['ROLE_ADMIN']); $em->persist($adm); $em->flush();
$b->loginUser($adm);
$cr = $b->request('GET', '/admin/positions/' . $s->getId()); t('admin 200', $b->getResponse()->getStatusCode() === 200);
$tok = $cr->filterXPath('//form[contains(@action,"/valider")]//input[@name="_token"]')->first()->attr('value');
$b->request('POST', '/admin/positions/position/' . $x->getId() . '/valider', ['_token' => 'faux']); t('CSRF invalide 403', $b->getResponse()->getStatusCode() === 403);
$b->request('POST', '/admin/positions/position/' . $x->getId() . '/valider', ['_token' => $tok]);
$b->request('GET', '/positions');
$h = $b->getResponse()->getContent();
t('position validée visible avec source', str_contains($h, $c->getName()) && str_contains($h, 'assemblee-nationale.fr'));
$a = new KernelBrowser($k); $a->disableReboot(); $a->request('GET', '/admin/positions'); t('admin fermé au public', in_array($a->getResponse()->getStatusCode(), [301, 302, 401, 403], true));
$em->createQuery('DELETE FROM App\Entity\PositionStance')->execute();
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
