<?php
// Filtre de moderation : variantes d'ecriture + signalement (incident enregistre, e-mails equipe et auteur).
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
$k = new App\Kernel('test', true); $k->boot();
$c = $k->getContainer()->get('test.service_container');
$m = $c->get(App\Service\ContentModerator::class);
$ok = 0; $ko = 0;
$t = function (string $label, bool $cond) use (&$ok, &$ko) { echo ($cond ? 'OK  ' : 'KO  ') . $label . "\n"; $cond ? $ok++ : $ko++; };
foreach (['Tu es un connard', 'espèce de c o n n a r d', 'c.o.n.n.a.r.d', 'quel conaaard', 'tu es un c0nnard', 'ferme ta gueule', 'quel connnard', 'conard'] as $s) {
    $t('bloque: ' . $s, $m->containsInsult($s));
}
foreach (['Je ne suis pas d\'accord avec cette mesure, elle coûte trop cher.', 'Le conseil constitutionnel a rendu sa décision.', 'La classe politique est responsable.', 'Il parle de PD, ONG et CAF', 'Les Mongols ont fondé un empire', 'Cette loi est une honte, elle doit être abrogée.'] as $s) {
    $t('laisse passer: ' . $s, !$m->containsInsult($s));
}
// Signalement : membre connecte, requete POST.
$em = $c->get('doctrine')->getManager();
$u = (new App\Entity\User())->setEmail('modtest' . time() . '@example.org')->setFirstName('Test')->setPassword('x');
$em->persist($u); $em->flush();
$c->get('security.token_storage')->setToken(new Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken($u, 'main', $u->getRoles()));
$rs = $c->get('request_stack'); $rs->push(Symfony\Component\HttpFoundation\Request::create('/propositions/nouvelle', 'POST'));
$before = (int) $em->createQuery('SELECT COUNT(i.id) FROM App\\Entity\\ModerationIncident i')->getSingleScalarResult();
$m->analyze('Tu es un connard et je vais te tuer');
$m->analyze('Tu es un connard et je vais te tuer'); // meme texte : une seule trace
$after = (int) $em->createQuery('SELECT COUNT(i.id) FROM App\\Entity\\ModerationIncident i')->getSingleScalarResult();
$t('un incident enregistre (doublon ignore)', $after === $before + 1);
$i = $em->getRepository(App\Entity\ModerationIncident::class)->findOneBy([], ['id' => 'DESC']);
$t('gravite haute pour menace', $i && $i->getSeverity() === 'haute');
$rs->pop(); $rs->push(Symfony\Component\HttpFoundation\Request::create('/propositions', 'GET'));
$m->analyze('Tu es une salope');
$t('GET ne signale pas', (int) $em->createQuery('SELECT COUNT(i.id) FROM App\\Entity\\ModerationIncident i')->getSingleScalarResult() === $after);
// Deuxieme avis Gemini (faux client HTTP, aucun appel reseau).
$rs->pop(); $rs->push(Symfony\Component\HttpFoundation\Request::create('/debats/1', 'POST'));
$mk = function (string $json) use ($c, $rs) {
    $client = new Symfony\Component\HttpClient\MockHttpClient(new Symfony\Component\HttpClient\Response\MockResponse(json_encode(['candidates' => [['content' => ['parts' => [['text' => $json]]]]]])));
    $ai = new App\Service\GeminiModerator($client, new Symfony\Component\Cache\Adapter\ArrayAdapter(), new Psr\Log\NullLogger(), $c->get('security.helper'), $rs, 'cle-de-test');
    return new App\Service\ContentModerator([], null, $ai);
};
$txt = 'Les gens comme vous ne méritent pas de vivre ici, disparaissez';
$t('IA bloque avec forte confiance', $mk('{"verdict":"bloquer","reasons":["haine"],"confidence":0.95}')->containsInsult($txt));
$t('IA faible confiance ne bloque pas', !$mk('{"verdict":"bloquer","reasons":["insulte"],"confidence":0.5}')->containsInsult($txt));
$t('IA doute ne bloque pas', !$mk('{"verdict":"douteux","reasons":["insulte"],"confidence":0.7}')->containsInsult($txt . ' bis'));
$t('reponse invalide = ok', !$mk('pas du json')->containsInsult($txt . ' ter'));
$t('texte propre non bloque', !$mk('{"verdict":"ok","reasons":[],"confidence":0.99}')->containsInsult('Je propose de financer la mesure par une taxe sur les rachats d actions.'));
$off = new App\Service\GeminiModerator(new Symfony\Component\HttpClient\MockHttpClient(), new Symfony\Component\Cache\Adapter\ArrayAdapter(), new Psr\Log\NullLogger(), $c->get('security.helper'), $rs, '');
$t('sans cle : aucun avis', $off->review($txt) === null);
echo "$ok OK, $ko KO\n"; exit($ko ? 1 : 0);
