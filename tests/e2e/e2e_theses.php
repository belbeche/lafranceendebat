<?php
// Analyses sourcées (feuille de route lycéens). Lancer : APP_ENV=test php tests/e2e/e2e_theses.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Command\ThesesRedigerCommand;
use App\Entity\{Thesis, User};
use App\Service\Positions\ThesisWriter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
// validation : garde-fous de la rédaction par Gemini
$raw = json_encode(['summary' => 'Résumé.', 'sections' => ['budget' => [['text' => 'Chiffre sourcé.', 'source_url' => 'https://www.education.gouv.fr/a'], ['text' => 'Chiffre sans source valide.', 'source_url' => 'https://blog-inconnu.example/x'], ['text' => 'Sans adresse.', 'source_url' => '']], 'evolution' => [], 'necessaire' => [], 'debat' => [], 'manques' => ['Coût total absent.']]]);
$v = ThesisWriter::validate($raw, ['education.gouv.fr']);
t('élément sourcé gardé', count($v['sections']['budget']) === 1);
t('domaine non autorisé écarté', count($v['sections']['budget']) === 1 && str_contains(implode(' ', $v['manques']), '2 élément(s) écarté(s)'));
t('réponse sans section = rien', ThesisWriter::validate('{"x":1}', ['a.fr']) === null);
t('analyse vide de sources = rien', ThesisWriter::validate(json_encode(['sections' => ['budget' => [['text' => 'x', 'source_url' => 'https://z.example/a']]]]), ['a.fr']) === null);
// rédaction complète avec Gemini simulé
$payload = json_encode(['candidates' => [['content' => ['parts' => [['text' => $raw]]], 'groundingMetadata' => ['groundingChunks' => []]]]]);
$w = new ThesisWriter(new MockHttpClient(fn () => new MockResponse($payload)), new Psr\Log\NullLogger(), 'cle');
$r = $w->write(['title' => 'Sujet', 'sources' => [['label' => 'Ministère', 'url' => 'https://www.education.gouv.fr/page']]]);
t('rédaction simulée acceptée', $r && count($r['sections']['budget']) === 1);
t('sans clé : null', (new ThesisWriter(new MockHttpClient(), new Psr\Log\NullLogger(), ''))->write(['title' => 'x', 'sources' => []]) === null);
// import du fichier de l'éditeur
$em->createQuery('DELETE FROM App\Entity\Thesis')->execute();
$cmd = new CommandTester(new ThesesRedigerCommand($em, $w));
$f = dirname(__DIR__, 2) . '/docs-publics/theses/lyceens-feuille-de-route-2026.json';
$cmd->execute(['fichier' => $f]);
$th = $em->getRepository(Thesis::class)->findOneBy(['slug' => 'lyceens-feuille-de-route-2026']);
t('analyse importée, masquée par défaut', $th && !$th->isApproved() && !$th->isAiGenerated());
$b = new KernelBrowser($k); $b->disableReboot(); $b->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr');
$b->request('GET', '/analyses/lyceens-feuille-de-route-2026'); t('masquée : 404', $b->getResponse()->getStatusCode() === 404);
$cmd->execute(['fichier' => $f, '--publier' => true]); $em->clear();
$b->request('GET', '/analyses/lyceens-feuille-de-route-2026'); $h = $b->getResponse()->getContent();
t('publiée : 200', $b->getResponse()->getStatusCode() === 200);
t('budget, évolution et nécessaire affichés', str_contains($h, 'Budget et financement') && str_contains($h, 'Évolution du texte') && str_contains($h, 'nécessaire pour l'));
t('critique attribuée à son auteur', str_contains($h, 'Selon le Syndicat de la magistrature'));
t('manques signalés', str_contains($h, 'Ce qu\'on ne trouve pas'));
$f2 = dirname(__DIR__, 2) . '/docs-publics/theses/loi-integrale-violences-sexistes-sexuelles-2026.json';
$cmd->execute(['fichier' => $f2, '--publier' => true]);
$b->request('GET', '/analyses/loi-integrale-violences-sexistes-sexuelles-2026'); $h2 = $b->getResponse()->getContent();
t('loi intégrale VSS publiée', $b->getResponse()->getStatusCode() === 200 && str_contains($h2, 'Selon Anne-Cécile Mailfert') && !str_contains($h2, 'fbclid'));
$b->request('GET', '/revendications'); t('VSS mise en évidence', str_contains($b->getResponse()->getContent(), 'Violences sexistes et sexuelles'));
$b->request('GET', '/revendications'); t('mise en évidence sur les revendications', str_contains($b->getResponse()->getContent(), 'En évidence'));
$g = new KernelBrowser($k); $g->disableReboot();
$adm = (new User())->setEmail('th' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('A')->setLastName('T')->setRoles(['ROLE_ADMIN']); $em->persist($adm); $em->flush();
$g->loginUser($adm); $cr = $g->request('GET', '/admin/analyses'); t('admin 200', $g->getResponse()->getStatusCode() === 200);
$fm = $cr->filter('form[action*="/approuver"]')->first(); $tok = $fm->filter('input[name=_token]')->attr('value'); preg_match('~/analyses/(\d+)/approuver~', $fm->attr('action'), $mm); $id = (int) $mm[1];
$was = $em->find(Thesis::class, $id)->isApproved();
$g->request('POST', "/admin/analyses/$id/approuver", ['_token' => $tok]); $em->clear();
t('bascule de la publication', $em->find(Thesis::class, $id)->isApproved() !== $was);
$g->request('POST', "/admin/analyses/$id/approuver", ['_token' => 'faux']); t('CSRF invalide 403', $g->getResponse()->getStatusCode() === 403);
$n = new KernelBrowser($k); $n->disableReboot(); $n->request('GET', '/admin/analyses'); t('admin fermé au public', in_array($n->getResponse()->getStatusCode(), [301, 302, 401, 403], true));
$em->createQuery('DELETE FROM App\Entity\Thesis')->execute();
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
