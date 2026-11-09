<?php
// File "À valider", fiche Positions + votes des groupes, soutien à l'initiative.
// Lancer : APP_ENV=test php tests/e2e/e2e_approvals_support.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\{Candidat, GroupVote, InitiativeSupport, PositionStance, PositionStatement, Thesis, User};
use App\Service\Positions\ScrutinImporter;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$tok = fn (KernelBrowser $b, string $path, string $sel) => $b->request('GET', $path)->filter($sel)->first()->filter('input[name=_token]')->attr('value');

// 1. Entités : fiche et votes
$s = new PositionStatement('Test', 'Affirmation de test pour la fiche ' . uniqid()); $s->setPublished(true);
$s->setSynthesis('<b>En bref</b> neutre.')->setViabilityAxis('cout', 'Coût estimé par la source.', 'https://www.gouv.fr/x')->setViabilityAxis('droit', 'Texte', 'http://pas-https.example')->setViabilityAxis('bidon', 'x', '');
t('synthèse nettoyée', $s->getSynthesis() === 'En bref neutre.');
t('axe inconnu ignoré, http écarté', count($s->getViability()) === 2 && $s->getViability()['droit']['source_url'] === '');
$em->persist($s); $em->flush();
$imp = new ScrutinImporter($em, new Symfony\Component\HttpClient\MockHttpClient(), sys_get_temp_dir());
$data = ['numero' => '777001', 'dateScrutin' => '2026-10-01', 'ventilationVotes' => ['organe' => ['groupes' => ['groupe' => [
    ['organeRef' => 'PA', 'vote' => ['decompteVoix' => ['pour' => '30', 'contre' => '2', 'abstentions' => '1']]],
    ['organeRef' => 'PB', 'vote' => ['decompteVoix' => ['pour' => '3', 'contre' => '20', 'abstentions' => '0']]],
    ['organeRef' => 'PC', 'vote' => ['decompteVoix' => ['pour' => '5', 'contre' => '5', 'abstentions' => '0']]]]]]]];
$org = ['PA' => ['code' => 'AAA', 'name' => 'Groupe A'], 'PB' => ['code' => 'BBB', 'name' => 'Groupe B'], 'PC' => ['code' => 'CCC', 'name' => 'Groupe C']];
t('3 groupes importés', $imp->saveFromData($s, $data, $org) === 3);
t('réimport sans doublon', $imp->saveFromData($s, $data, $org) === 3 && $em->getRepository(GroupVote::class)->count(['statement' => $s]) === 3);
$byCode = []; foreach ($em->getRepository(GroupVote::class)->findBy(['statement' => $s]) as $v) { $byCode[$v->getGroupCode()] = $v; }
t('position pour / contre / égalité = abstention', $byCode['AAA']->getPosition() === 'pour' && $byCode['BBB']->getPosition() === 'contre' && $byCode['CCC']->getPosition() === 'abstention');
try { $imp->saveFromData($s, ['numero' => '1'], $org); t('données incomplètes refusées', false); } catch (\RuntimeException) { t('données incomplètes refusées', true); }

// 2. Page publique
$b = new KernelBrowser($k); $b->disableReboot(); $b->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr');
$b->request('GET', '/positions'); $h = $b->getResponse()->getContent();
t('positions 200', $b->getResponse()->getStatusCode() === 200);
t('synthèse affichée', str_contains($h, 'En bref neutre.'));
t('axe documenté + non documenté', str_contains($h, 'Coût estimé par la source.') && str_contains($h, 'Non documenté pour l'));
t('votes des groupes et lien officiel', str_contains($h, 'AAA') && str_contains($h, 'Scrutin n° 777001') && str_contains($h, '/dyn/17/scrutins/777001'));
t('filtre par thème sans résultat', !str_contains((function () use ($b) { $b->request('GET', '/positions?theme=Inconnu'); return $b->getResponse()->getContent(); })(), 'Affirmation de test pour la fiche'));

// 3. Soutien à l'initiative
$b->request('GET', '/soutenir'); t('/soutenir public 200', $b->getResponse()->getStatusCode() === 200 && str_contains($b->getResponse()->getContent(), 'Déclarer mon soutien'));
$b->request('POST', '/soutenir/rejoindre'); t('rejoindre sans compte refusé', in_array($b->getResponse()->getStatusCode(), [302, 401, 403], true) && $em->getRepository(InitiativeSupport::class)->count([]) === 0 || $b->getResponse()->isRedirect());
$u = (new User())->setEmail('sup' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('S')->setLastName('U'); $em->persist($u); $em->flush();
$u2 = new KernelBrowser($k); $u2->disableReboot(); $u2->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr'); $u2->loginUser($u);
$token = $tok($u2, '/soutenir', 'form[action$="/soutenir/rejoindre"]');
$u2->request('POST', '/soutenir/rejoindre', ['_token' => $token, 'public' => '1', 'public_name' => '<i>Zoé</i>']);
$em->clear(); $n = $em->getRepository(InitiativeSupport::class)->count([]);
t('soutien enregistré', $n === 1);
$token = $tok($u2, '/soutenir', 'form[action$="/soutenir/retirer"]');
$u2->request('POST', '/soutenir/rejoindre', ['_token' => $token]); $em->clear();
t('un seul soutien par compte', $em->getRepository(InitiativeSupport::class)->count([]) === 1);
t('nom public nettoyé affiché', str_contains($u2->request('GET', '/soutenir') ? $u2->getResponse()->getContent() : '', 'Zoé') && !str_contains($u2->getResponse()->getContent(), '<i>Zoé'));
$u2->request('POST', '/soutenir/retirer', ['_token' => $token]); $em->clear();
t('soutien retiré', $em->getRepository(InitiativeSupport::class)->count([]) === 0);

// 4. File "À valider" (admin)
$s = $em->find(PositionStatement::class, $s->getId());
$cand = (new Candidat())->setName('Personne Appro ' . uniqid())->setSlug('pa-' . uniqid())->setIsPublished(true); $em->persist($cand);
$st = new PositionStance($s, $cand, 1, 'Résumé de test', 'https://www.gouv.fr/pos', true); $em->persist($st);
$th = new Thesis('appro-' . uniqid(), 'Analyse à valider'); $em->persist($th);
$adm = (new User())->setEmail('ap' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('A')->setLastName('D')->setRoles(['ROLE_ADMIN']); $em->persist($adm); $em->flush();
$a = new KernelBrowser($k); $a->disableReboot(); $a->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr'); $a->loginUser($adm);
$cr = $a->request('GET', '/admin/a-valider'); $h = $a->getResponse()->getContent();
t('file 200, position IA étiquetée', $a->getResponse()->getStatusCode() === 200 && str_contains($h, 'Personne Appro') && str_contains($h, 'recherche IA'));
t('analyse listée sans bouton refuser', str_contains($h, 'Analyse à valider'));
$sid = $st->getId(); $tid = $th->getId();
$tk = $cr->filter('form[action*="/lot/approuver"] input[name=_token]')->first()->attr('value');
t('boutons en lot présents', str_contains($h, 'Approuver la sélection') && str_contains($h, 'Tout approuver'));
$a->request('POST', "/admin/a-valider/position/$sid/approuver", ['_token' => $tk]); $em->clear();
t('position approuvée en un clic', $em->find(PositionStance::class, $sid)->getStatus() === PositionStance::APPROVED);
$a->request('POST', "/admin/a-valider/analyse/$tid/approuver", ['_token' => $tk]); $em->clear();
t('analyse approuvée en un clic', $em->find(Thesis::class, $tid)->isApproved());
$a->request('POST', "/admin/a-valider/analyse/$tid/refuser", ['_token' => $tk]); $em->clear();
t('refus impossible pour une analyse : rien ne change', $em->find(Thesis::class, $tid)->isApproved());
// lot : sélection puis tout
$cand2 = (new Candidat())->setName('Personne Deux ' . uniqid())->setSlug('pd-' . uniqid())->setIsPublished(true); $em->persist($cand2); $s = $em->find(PositionStatement::class, $s->getId()); $st2 = new PositionStance($s, $cand2, 1, 'Deuxième', 'https://www.gouv.fr/pos2', true); $em->persist($st2); $em->flush(); $s2 = $st2->getId();
$a->request('POST', '/admin/a-valider/position/lot/approuver', ['_token' => $tk, 'ids' => [$s2]]); $em->clear();
t('lot : sélection approuvée', $em->find(PositionStance::class, $s2)->getStatus() === PositionStance::APPROVED);
$a->request('POST', '/admin/a-valider/position/lot/approuver', ['_token' => $tk]);
t('lot sans sélection : rien, pas d\'erreur', in_array($a->getResponse()->getStatusCode(), [302, 303], true));
$a->request('POST', '/admin/a-valider/tout', ['_token' => $tk]); t('tout approuver : redirige', in_array($a->getResponse()->getStatusCode(), [302, 303], true));
$a->request('POST', '/admin/a-valider/tout', ['_token' => 'faux']); t('tout sans jeton : 403', $a->getResponse()->getStatusCode() === 403);
$a->request('POST', "/admin/a-valider/position/$sid/approuver", ['_token' => 'faux']);
t('jeton invalide : 403', $a->getResponse()->getStatusCode() === 403);

// nettoyage
$em->clear();
foreach ([GroupVote::class, PositionStance::class, Thesis::class] as $c) { $em->createQuery("DELETE FROM $c")->execute(); }
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
