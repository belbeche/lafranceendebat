<?php
// Espace des partis. Lancer : APP_ENV=test php tests/e2e/e2e_parties.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\{PartyProposal, PartyProfile, User};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$em->createQuery("DELETE FROM App\Entity\PartyProposal p WHERE p.externalId LIKE 'tpa-%'")->execute();
$em->createQuery("DELETE FROM App\Entity\PartyProfile p WHERE p.groupCode IN ('ZZA','ZZB')")->execute();
$mk = function ($id, $title, $code, $name) use ($em) { $p = (new PartyProposal())->setExternalId($id)->setTitle($title)->setGroup($code, $name)->setSourceUrl('https://example.org/' . $id); $em->persist($p); return $p; };
$a1 = $mk('tpa-1', 'Mesure Zeta un', 'ZZA', 'Zeta Alpha'); $a1->setCost(1000, 'A', 'https://example.org/c1');
$a2 = $mk('tpa-2', 'Mesure Zeta deux', 'ZZA', 'Zeta Alpha'); $a2->setCost(-200, 'B', 'https://example.org/c2');
$a3 = $mk('tpa-3', 'Mesure Zeta trois', 'ZZA', 'Zeta Alpha');
$b1 = $mk('tpa-4', 'Mesure Beta un', 'ZZB', 'Zeta Beta');
$em->flush();
$b = new KernelBrowser($k); $b->disableReboot(); $b->setServerParameter('HTTP_ACCEPT_LANGUAGE', 'fr');
$b->request('GET', '/partis'); $h = $b->getResponse()->getContent();
t('liste 200, les deux formations', $b->getResponse()->getStatusCode() === 200 && str_contains($h, 'Zeta Alpha') && str_contains($h, 'Zeta Beta'));
t('ordre alphabetique (Alpha avant Beta)', strpos($h, 'Zeta Alpha') < strpos($h, 'Zeta Beta'));
t('comptes : 3 propositions, 2 chiffrees', str_contains($h, '3 propositions · 2 chiffrées'));
t('lien vers comparateur independant, sans reprise de notation', str_contains($h, 'monvote2027.fr') && str_contains($h, 'ni les notations'));
$b->request('GET', '/partis/ZZA'); $h = $b->getResponse()->getContent();
t('fiche 200', $b->getResponse()->getStatusCode() === 200 && str_contains($h, 'Mesure Zeta trois'));
t('niveau A = 1 000 et B = -200 (sourcés)', str_contains($h, '1 000') && str_contains($h, '-200'));
$b->request('GET', '/partis/NOPE9'); t('formation inconnue : 404', $b->getResponse()->getStatusCode() === 404);
// hypothese IA : invisible puis visible apres validation, a part
$a3 = $em->find(PartyProposal::class, $a3->getId());
$a3->setAiAnalysis(['costable' => true, 'scenarios' => ['low' => 10, 'central' => 20, 'high' => 40], 'reliability' => 40, 'params' => [], 'formula' => 'x', 'argument_for' => 'a', 'argument_against' => 'b', 'missing_data' => []], 'm'); $em->flush();
$b->request('GET', '/partis/ZZA'); t('IA non validee : absente', !str_contains($b->getResponse()->getContent(), 'hypothèses IA non officielles'));
$a3 = $em->find(PartyProposal::class, $a3->getId()); $a3->setAiApproved(true); $em->flush();
$b->request('GET', '/partis/ZZA'); $h = $b->getResponse()->getContent();
t('IA validee : a part, 10 a 40', str_contains($h, 'hypothèses IA non officielles') && str_contains($h, 'de 10 à 40'));
t('niveaux sourcés inchangés (pas de 1 020)', !str_contains($h, '1 020'));
// admin liens officiels
$adm = (new User())->setEmail('pp' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('A')->setLastName('D')->setRoles(['ROLE_ADMIN']); $em->persist($adm); $em->flush();
$ab = new KernelBrowser($k); $ab->disableReboot(); $ab->loginUser($adm);
$cr = $ab->request('GET', '/admin/programmes/partis/ZZA'); t('admin : page liens 200', $ab->getResponse()->getStatusCode() === 200);
$tok = $cr->filterXPath('//form[.//input[@name="official_site"]]//input[@name="_token"]')->attr('value');
$ab->request('POST', '/admin/programmes/partis/ZZA', ['_token' => $tok, 'official_site' => 'http://pas-https.example', 'programme_url' => '']); t('http refuse', str_contains($ab->getResponse()->getContent(), 'adresse https'));
$ab->request('POST', '/admin/programmes/partis/ZZA', ['_token' => $tok, 'official_site' => 'https://zeta.example.org', 'programme_url' => 'https://zeta.example.org/programme-2027']);
$b->request('GET', '/partis/ZZA'); $h = $b->getResponse()->getContent();
t('liens officiels affiches', str_contains($h, 'https://zeta.example.org/programme-2027') && str_contains($h, 'Site officiel'));
$cr = $ab->request('GET', '/admin/programmes/partis/ZZA');
$ab->request('POST', '/admin/programmes/partis/ZZA', ['_token' => 'faux', 'official_site' => 'https://zeta.example.org']); t('jeton invalide : 403', $ab->getResponse()->getStatusCode() === 403);
$pub = new KernelBrowser($k); $pub->disableReboot(); $pub->request('GET', '/admin/programmes/partis/ZZA'); t('admin ferme au public', in_array($pub->getResponse()->getStatusCode(), [301, 302, 401, 403], true));
$em->createQuery("DELETE FROM App\Entity\PartyProposal p WHERE p.externalId LIKE 'tpa-%'")->execute();
$em->createQuery("DELETE FROM App\Entity\PartyProfile p WHERE p.groupCode IN ('ZZA','ZZB')")->execute();
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
