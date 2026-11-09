<?php
// Enquetes de terrain : modeles, saisie terrain, proces-verbal et empreinte.
// Lancer : APP_ENV=test MAIL_FROM=no-reply@lafranceendebat.fr php tests/e2e/e2e_survey_field.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\{Survey, SurveyResponse, User, Proposition};
use App\Service\Survey\{SurveyTemplates, SurveyResults};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
// modeles : chacun doit etre parsable sans erreur
foreach (array_keys(SurveyTemplates::LABELS) as $key) {
    $m = SurveyTemplates::get($key); [$q, $e] = Survey::parse($m['questions']);
    t("modele $key valide", $q && !$e && mb_strlen($m['intro']) >= 20);
}
t('themes importes dans le modele', str_contains(SurveyTemplates::get('priorites')['questions'], Proposition::THEMES[0]));
$u = (new User())->setEmail('fd' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('F')->setLastName('D')->setRoles(['ROLE_ADMIN']);
$em->persist($u); $em->flush();
$b = new KernelBrowser($k); $b->loginUser($u);
$b->request('GET', '/admin/enquetes/nouvelle?modele=terrain');
t('modele prerempli dans le formulaire', str_contains($b->getResponse()->getContent(), 'Enquête de terrain'));
$s = (new Survey())->setTitle('Terrain test ' . uniqid())->setIntro('Une presentation assez longue pour le test de terrain.');
[$q] = Survey::parse("choix* | Priorite ? | Sante ; Ecole\nechelle | Satisfaction\ntexte | Idee ?"); $s->setQuestions($q)->publish();
$em->persist($s); $em->flush();
$cr = $b->request('GET', '/admin/enquetes/' . $s->getId() . '/saisie-terrain');
t('formulaire de saisie 200', $b->getResponse()->getStatusCode() === 200);
$tok = $cr->filter('main input[name=_token]')->attr('value');
$b->request('POST', '/admin/enquetes/' . $s->getId() . '/saisie-terrain', ['_token' => $tok, 'place' => 'Marche de Test', 'q' => [0 => 'Sante', 1 => '4', 2 => 'Plus de bus']]);
t('saisie valide redirige', $b->getResponse()->isRedirect());
$b->request('POST', '/admin/enquetes/' . $s->getId() . '/saisie-terrain', ['_token' => $tok, 'place' => '', 'q' => [0 => 'Sante']]);
t('lieu obligatoire', $b->getResponse()->getStatusCode() === 422);
$b->request('POST', '/admin/enquetes/' . $s->getId() . '/saisie-terrain', ['_token' => $tok, 'place' => 'Marche de Test', 'q' => [1 => '3']]);
t('question obligatoire refusee', $b->getResponse()->getStatusCode() === 422);
$em->clear(); $s = $em->find(Survey::class, $s->getId());
$r = $k->getContainer()->get('test.service_container')->get(SurveyResults::class);
t('1 reponse terrain comptee', $r->count($s) === 1 && $r->channels($s)['terrain'] === 1);
$h1 = $r->fingerprint($s);
t('empreinte stable', $h1 === $r->fingerprint($s) && strlen($h1) === 64);
$b->request('GET', '/admin/enquetes/' . $s->getId() . '/proces-verbal');
$html = $b->getResponse()->getContent();
t('PV 200 avec empreinte', $b->getResponse()->getStatusCode() === 200 && str_contains($html, $h1));
t('PV mentionne les limites et le lieu', str_contains($html, 'non représentatif') || str_contains($html, 'pas représentatif') || str_contains($html, 'n\'est donc pas représentatif'));
t('PV lieu de collecte', str_contains($html, 'Marche de Test'));
$em->persist(new SurveyResponse($s, 'terrain-x' . uniqid(), 'terrain', [0 => 'Ecole', 1 => '2', 2 => '']));
$em->flush();
t('empreinte change si une reponse est ajoutee', $r->fingerprint($s) !== $h1);
$b->request('GET', '/admin/enquetes/' . $s->getId() . '/export.csv');
t('CSV avec canal et lieu', str_contains($b->getInternalResponse()->getContent(), 'canal;lieu'));
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
