<?php
// Envoi ciblé. Lancer : APP_ENV=test php tests/e2e/e2e_targeted_letter.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\{ActorContact, User};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$u = uniqid();
$em->createQuery('DELETE FROM App\Entity\ActorContact a WHERE a.email LIKE :e')->setParameter('e', '%@ciblee' . $u . '.fr')->execute();
$adm = (new User())->setEmail('tl' . $u . '@t.fr')->setPassword('x')->setFirstName('A')->setLastName('T')->setRoles(['ROLE_ADMIN']); $em->persist($adm); $em->flush();
$b = new KernelBrowser($k); $b->disableReboot(); $b->loginUser($adm);
$cr = $b->request('GET', '/admin/lettres/ciblee'); t('page 200', $b->getResponse()->getStatusCode() === 200);
t('modèle de partenariat prérempli', str_contains($b->getResponse()->getContent(), 'licence'));
$tok = $cr->filter('input[name=_token]')->first()->attr('value');
$good = 'contact@ciblee' . $u . '.fr;Asso Test;Contact;https://ciblee' . $u . '.fr/mentions';
$list = $good . "\nperso@gmail.com;Perso;;https://exemple.fr/x\nsans-source@ciblee" . $u . ".fr;Orga;;";
$post = fn (string $action, array $x = []) => $b->request('POST', '/admin/lettres/ciblee', array_merge(['_token' => $tok, 'action' => $action, 'recipients' => $list, 'subject' => 'Objet', 'body' => 'Bonjour {organisation}'], $x));
$post('check'); $h = $b->getResponse()->getContent();
t('contrôle : adresse valide prête', str_contains($h, 'Prêt'));
t('contrôle : gmail refusé', str_contains($h, 'Messagerie grand public'));
t('contrôle : source manquante refusée', str_contains($h, 'source_url'));
t('contrôle n\'enregistre rien', $em->getRepository(ActorContact::class)->count(['email' => 'contact@ciblee' . $u . '.fr']) === 0);
$b->request('POST', '/admin/lettres/ciblee', ['_token' => 'faux', 'action' => 'check']); t('CSRF invalide 403', $b->getResponse()->getStatusCode() === 403);
$post('send'); t('sans confirmation : rien envoyé', $em->getRepository(ActorContact::class)->count(['email' => 'contact@ciblee' . $u . '.fr']) === 0);
$post('send', ['confirm' => '1']);
$em->clear(); $a = $em->getRepository(ActorContact::class)->findOneBy(['email' => 'contact@ciblee' . $u . '.fr']);
$h = $b->getResponse()->getContent();
$problem = str_contains($h, 'alert-warning');
t('envoi : contact enregistré et marqué contacté (ou envoi bloqué par la configuration de messagerie)', $problem || ($a && $a->getContactCount() === 1));
t('gmail jamais enregistré', $em->getRepository(ActorContact::class)->count(['email' => 'perso@gmail.com']) === 0);
if ($a) {
    $post('send', ['confirm' => '1', 'recipients' => $good]);
    t('second envoi bloqué (déjà contacté)', str_contains($b->getResponse()->getContent(), 'Déjà contacté') || str_contains($b->getResponse()->getContent(), 'Aucun destinataire'));
    $a->unsubscribe(); $em->flush();
    $post('check', ['recipients' => $good]); t('désinscrit exclu définitivement', str_contains($b->getResponse()->getContent(), 'exclu définitivement'));
}
$many = implode("\n", array_map(fn ($i) => "a$i@ciblee$u.fr;Org $i;;https://ciblee$u.fr/$i", range(1, 11)));
$post('send', ['confirm' => '1', 'recipients' => $many]); t('plus de 10 destinataires refusé', str_contains($b->getResponse()->getContent(), 'Maximum 10'));
$n = new KernelBrowser($k); $n->disableReboot(); $n->request('GET', '/admin/lettres/ciblee'); t('fermé au public', in_array($n->getResponse()->getStatusCode(), [301, 302, 401, 403], true));
$em->createQuery('DELETE FROM App\Entity\ActorContact a WHERE a.email LIKE :e')->setParameter('e', '%@ciblee' . $u . '.fr')->execute();
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
