<?php
// Hypotheses IA : calcul en code, plafond, validation moderateur, total separe. Lancer : APP_ENV=test php tests/e2e/e2e_ai_estimate.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\{PartyProposal, User};
use App\Service\Programme\{CostModel, ProposalAnalyst};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$P = fn ($l, $c, $h) => ['low' => $l, 'central' => $c, 'high' => $h, 'basis' => 'INSEE'];
$params = ['population' => $P(1000000, 2000000, 3000000), 'unit_cost_eur' => $P(500, 1000, 1500), 'take_up_pct' => $P(50, 70, 90),
    'fixed_millions' => $P(0, 10, 20), 'offset_millions' => $P(0, 100, 200)];
$r = CostModel::compute($params);
// central : 2e6*1000*0.7/1e6 = 1400 + 10 - 100 = 1310 ; bas : 1e6*500*.5/1e6=250+0-200=50 ; haut : 3e6*1500*.9/1e6=4050+20-0=4070
t('central calcule en code', $r['central'] === 1310); t('bas', $r['low'] === 50); t('haut', $r['high'] === 4070);
t('formule lisible', str_contains((string) CostModel::explain($params), '2 000 000 personnes'));
$bad = $params; $bad['take_up_pct'] = $P(50, 70, 140); t('taux > 100 % refuse', null === CostModel::compute($bad));
$bad = $params; $bad['population'] = $P(3, 2, 1); t('bas > haut refuse', null === CostModel::compute($bad));
$bad = $params; unset($bad['unit_cost_eur']); t('parametre manquant refuse', null === CostModel::compute($bad));
$raw = json_encode(['costable' => true, 'params' => $params, 'argument_for' => 'a<b>x</b>', 'argument_against' => 'c', 'reliability' => 99, 'missing_data' => ['d'], 'result' => 1]);
$a = ProposalAnalyst::parse($raw);
t('fiabilite plafonnee a 70', $a['reliability'] === 70); t('le chiffre de l\'IA est ignore, recalcule', $a['scenarios']['central'] === 1310);
t('html retire', !str_contains($a['argument_for'], '<'));
t('JSON invalide : null', null === ProposalAnalyst::parse('nimportequoi'));
$nc = ProposalAnalyst::parse(json_encode(['costable' => false, 'not_costable_reason' => 'symbolique', 'params' => [], 'argument_for' => '', 'argument_against' => '', 'reliability' => 50, 'missing_data' => []]));
t('non chiffrable : fiabilite 0', $nc['costable'] === false && $nc['reliability'] === 0);
$inc = ProposalAnalyst::parse(json_encode(['costable' => true, 'params' => $bad, 'argument_for' => '', 'argument_against' => '', 'reliability' => 60, 'missing_data' => []]));
t('hypotheses incoherentes : rien retenu', $inc['costable'] === false);
// calculateur
$em->createQuery('DELETE FROM App\Entity\PartyProposal p WHERE p.externalId LIKE :x')->setParameter('x', 'tai-%')->execute();
$mk = function ($id, $title) use ($em) { $p = (new PartyProposal())->setExternalId($id)->setTitle($title)->setGroup('TST', 'Test')->setSourceUrl('https://example.org/' . $id); $em->persist($p); return $p; };
$s = $mk('tai-s', 'Mesure sourcee test'); $s->setCost(1000, 'A', 'https://example.org/chiffre');
$i = $mk('tai-i', 'Mesure hypothese IA test'); $i->setAiAnalysis($a, 'gemini-test');
$em->flush();
t('IA non validee : invisible', !$i->isAiPublic());
$b = new KernelBrowser($k); $b->disableReboot();
$b->request('GET', '/calculateur-budget?ids[]=' . $s->getId() . '&ids[]=' . $i->getId() . '&ia=1'); $h = $b->getResponse()->getContent();
t('non validee : absente du calculateur', !str_contains($h, 'Mesure hypothese IA test'));
$b->request('GET', '/programmes/' . $i->getId()); t('non validee : fiche sans hypothese', !str_contains($b->getResponse()->getContent(), 'non officielle'));
$iid = $i->getId(); $em->clear(); $i = $em->find(PartyProposal::class, $iid); $i->setAiApproved(true); $em->flush();
$b->request('GET', '/calculateur-budget?ids[]=' . $s->getId() . '&ids[]=' . $i->getId() . '&ia=1'); $h = $b->getResponse()->getContent();
t('validee : proposee avec etiquette IA', str_contains($h, 'Mesure hypothese IA test') && str_contains($h, 'hypothèse IA'));
t('sourcé : 1 000 M€ seulement', str_contains($h, 'Chiffrages sourcés : 1 000 millions') );
t('IA a part : 50 a 4 070', str_contains($h, '50 à 4 070') && str_contains($h, 'Non ajoutées'));
$b->request('GET', '/calculateur-budget?ids[]=' . $s->getId() . '&ids[]=' . $i->getId()); t('sans la case IA : pas de total IA', !str_contains($b->getResponse()->getContent(), 'Non ajoutées'));
$b->request('GET', '/programmes/' . $i->getId()); $f = $b->getResponse()->getContent();
t('fiche : hypothese etiquetee et formule', str_contains($f, 'non officielle') && str_contains($f, '2 000 000 personnes'));
// admin : csrf et droits
$adm = (new User())->setEmail('ai' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('A')->setLastName('D')->setRoles(['ROLE_ADMIN']); $em->persist($adm); $em->flush();
$ab = new KernelBrowser($k); $ab->disableReboot(); $ab->loginUser($adm);
$ab->request('POST', '/admin/programmes/' . $i->getId() . '/ia/retirer', ['_token' => 'faux']); t('retrait sans jeton : 403', $ab->getResponse()->getStatusCode() === 403);
$cr = $ab->request('GET', '/admin/programmes/' . $i->getId()); t('admin : page 200', $ab->getResponse()->getStatusCode() === 200);
$tok = $cr->filterXPath('//form[contains(@action,"/ia/retirer")]//input[@name="_token"]')->attr('value');
$ab->request('POST', '/admin/programmes/' . $i->getId() . '/ia/retirer', ['_token' => $tok]); $em->clear();
t('retrait OK', null === $em->find(PartyProposal::class, $i->getId())->getAiAnalysis());
$pub = new KernelBrowser($k); $pub->disableReboot(); $pub->request('POST', '/admin/programmes/' . $i->getId() . '/ia/valider'); t('public : refuse', in_array($pub->getResponse()->getStatusCode(), [301, 302, 401, 403], true));
$em->createQuery('DELETE FROM App\Entity\PartyProposal p WHERE p.externalId LIKE :x')->setParameter('x', 'tai-%')->execute();
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
