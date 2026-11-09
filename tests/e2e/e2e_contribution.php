<?php
// Contribution de soutien SANS reçu fiscal : DONATIONS_OPEN=1, DONATION_RECEIPTS_ENABLED=0 (cas de la micro-entreprise).
// Lancer : DONATIONS_OPEN=1 DONATION_RECEIPTS_ENABLED=0 APP_ENV=test php tests/e2e/e2e_contribution.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\Donation;
use App\Service\Payment\Testing\FakeGateway;
$k = new App\Kernel('test', true); $k->boot();
$c = $k->getContainer()->get('test.client'); $c->disableReboot();
$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang', 'fr'));
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$c->request('GET', '/soutenir/contribution');
if (getenv('DONATIONS_OPEN') !== '1') { t('/soutenir/contribution 404 si fermé', $c->getResponse()->getStatusCode() == 404); echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0); }
t('formulaire 200', $c->getResponse()->getStatusCode() == 200);
t('pas de champ adresse sans reçu', $c->getCrawler()->filter('input[name=address]')->count() === 0);
t('aucune promesse fiscale', !preg_match('/reçu fiscal ouvre|réduction d.impôt\./u', $c->getResponse()->getContent()) || str_contains($c->getResponse()->getContent(), 'pas droit'));
$tok = $c->getCrawler()->filter('main input[name=_token]')->attr('value');
$u = uniqid();
$p = ['_token' => $tok, 'name' => 'Jeanne ' . $u, 'email' => $u . '@example.org', 'amount' => '10', 'no_political' => '1'];
FakeGateway::$calls = [];
$c->request('POST', '/soutenir/contribution', $p);
t('redirige vers Stripe sans adresse', $c->getResponse()->isRedirect());
$em = $c->getContainer()->get('doctrine')->getManager(); $em->clear();
$d = $em->getRepository(Donation::class)->findOneBy(['donorEmail' => $u . '@example.org'], ['id' => 'DESC']);
t('contribution créée en attente (1000 centimes)', $d && $d->getStatus() === 'attente_paiement' && $d->getAmountCents() === 1000);
$evt = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => $d->getStripeSessionId(), 'payment_status' => 'paid', 'amount_total' => 1000, 'metadata' => ['donation_id' => (string) $d->getId()]]]]);
$ts = time(); $sig = 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $evt, 'whsec_test_secret');
$c->request('POST', '/webhook/stripe', [], [], ['HTTP_STRIPE_SIGNATURE' => $sig], $evt);
$em->clear(); $d = $em->find(Donation::class, $d->getId());
t('webhook : payée, sans reçu', $d->getStatus() === 'payee' && $d->getReceipt() === null);
$c->request('POST', '/webhook/stripe', [], [], ['HTTP_STRIPE_SIGNATURE' => $sig], $evt);
$em->clear(); $d = $em->find(Donation::class, $d->getId());
t('webhook rejoué : toujours payée, aucun reçu', $d->getStatus() === 'payee' && $d->getReceipt() === null);
$c->request('GET', '/soutenir/merci/' . $d->getToken());
$html = $c->getResponse()->getContent();
t('page merci : confirmation, pas de reçu', $c->getResponse()->getStatusCode() == 200 && str_contains($html, 'bien reçue') && !str_contains($html, 'Télécharger'));
$c->request('GET', '/soutenir/recu/' . $d->getToken() . '.pdf');
t('reçu PDF introuvable', $c->getResponse()->getStatusCode() == 404);
$bad = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_x', 'payment_status' => 'paid', 'amount_total' => 1, 'metadata' => ['donation_id' => '99999999']]]]);
$sig2 = 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $bad, 'wrong');
$c->request('POST', '/webhook/stripe', [], [], ['HTTP_STRIPE_SIGNATURE' => $sig2], $bad);
t('signature invalide rejetée', $c->getResponse()->getStatusCode() >= 400);
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
