<?php
// Gestion admin des contributions. Lancer : APP_ENV=test php tests/e2e/e2e_donation_admin.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\{Donation, User};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$em->createQuery('DELETE FROM App\Entity\Donation')->execute();
$mk = function (int $cents, string $status) use ($em) { $d = new Donation('Test ' . uniqid(), '', 'd' . uniqid() . '@t.fr', $cents); $d->setStatus($status); $em->persist($d); $em->flush(); return $d; };
$p = []; for ($i = 0; $i < 4; $i++) { $p[] = $mk(500000, Donation::PENDING); }
$paid = $mk(2500, Donation::PAID);
$adm = (new User())->setEmail('da' . uniqid() . '@t.fr')->setPassword('x')->setFirstName('A')->setLastName('D')->setRoles(['ROLE_ADMIN']); $em->persist($adm); $em->flush();
$b = new KernelBrowser($k); $b->disableReboot(); $b->loginUser($adm);
$cr = $b->request('GET', '/admin/contributions'); $h = $b->getResponse()->getContent();
t('tableau de bord 200', $b->getResponse()->getStatusCode() === 200);
t('en attente : 4 · 20 000,00 €', str_contains($h, '4 · 20 000,00') );
t('payees : 1 · 25,00 €', str_contains($h, '1 · 25,00'));
t('mise en garde : attente != recettes', str_contains($h, 'Pas du chiffre d'));
$tok = $cr->filter('form input[name=_token]')->first()->attr('value');
$b->request('GET', '/admin/contributions/' . $p[0]->getId()); t('detail 200', $b->getResponse()->getStatusCode() === 200);
$cr = $b->request('GET', '/admin/contributions/' . $p[0]->getId()); $t0 = $cr->filterXPath('//form[.//button[contains(@formaction,"/annuler")]]//input[@name="_token"]')->attr('value');
$b->request('POST', '/admin/contributions/' . $p[0]->getId() . '/annuler', ['_token' => 'faux']); t('CSRF invalide : 403', $b->getResponse()->getStatusCode() === 403);
$b->request('POST', '/admin/contributions/' . $p[0]->getId() . '/annuler', ['_token' => $t0]);
$em->clear(); t('annulation d\'une attente', $em->find(Donation::class, $p[0]->getId())->getStatus() === 'annulee');
$cr = $b->request('GET', '/admin/contributions/' . $p[1]->getId()); $t1 = $cr->filterXPath('//form[.//button[contains(@formaction,"/supprimer")]]//input[@name="_token"]')->attr('value');
$b->request('POST', '/admin/contributions/' . $p[1]->getId() . '/supprimer', ['_token' => $t1]);
$em->clear(); t('suppression d\'une attente', null === $em->find(Donation::class, $p[1]->getId()));
$cr = $b->request('GET', '/admin/contributions/' . $paid->getId()); t('payee : pas de bouton supprimer', !str_contains($b->getResponse()->getContent(), '/supprimer'));
// jeton valide pour la contribution payee : on la rend brievement "en attente" pour lire le formulaire, puis on la repasse payee
$row = $em->find(Donation::class, $paid->getId()); $row->setStatus(Donation::PENDING); $em->flush();
$tp = $b->request('GET', '/admin/contributions/' . $paid->getId())->filterXPath('//form[.//button[contains(@formaction,"/supprimer")]]//input[@name="_token"]')->attr('value');
$row = $em->find(Donation::class, $paid->getId()); $row->setStatus(Donation::PAID); $em->flush();
$em->clear(); t('precondition : payee', $em->find(Donation::class, $paid->getId())->getStatus() === 'payee');
$b->request('POST', '/admin/contributions/' . $paid->getId() . '/supprimer', ['_token' => $tp]);
$em->clear(); t('payee jamais supprimee', null !== $em->find(Donation::class, $paid->getId()));
$b->request('POST', '/admin/contributions/' . $paid->getId() . '/annuler', ['_token' => $tp]); $em->clear();
t('payee jamais annulee', $em->find(Donation::class, $paid->getId())->getStatus() === 'payee');
$b->request('GET', '/admin/contributions/export.csv'); $csv = $b->getInternalResponse()->getContent();
t('export CSV sans adresse postale', str_contains($csv, 'id;date;statut;montant_eur;nom;email') && !str_contains($csv, 'adresse'));
$a = new KernelBrowser($k); $a->disableReboot(); $a->request('GET', '/admin/contributions'); t('ferme au public', in_array($a->getResponse()->getStatusCode(), [301, 302, 401, 403], true));
$em->createQuery('DELETE FROM App\Entity\Donation')->execute();
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
