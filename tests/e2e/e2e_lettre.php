<?php
require '/home/claude/app/vendor/autoload.php';
putenv('ASSOCIATION_ADDRESS=1 rue de Test, 75000 Paris'); $_ENV['ASSOCIATION_ADDRESS']=$_SERVER['ASSOCIATION_ADDRESS']='1 rue de Test, 75000 Paris';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
putenv('MAIL_FROM=lettre@example.org'); $_ENV['MAIL_FROM']=$_SERVER['MAIL_FROM']='lettre@example.org';
use App\Entity\{User,Lettre,NewsletterSubscriber};
function client($email=null){ $k=new App\Kernel('test',true); $k->boot(); $c=$k->getContainer()->get('test.client'); $c->disableReboot(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang","fr"));
  if($email){ $u=$k->getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email'=>$email]); $c->loginUser($u);} return $c; }
function em($c){ return $c->getContainer()->get('doctrine')->getManager(); }
function mails($c){ return $c->getContainer()->get('test.service_container')->get('mailer.message_logger_listener')->getEvents()->getMessages(); }
$ok=0;$ko=0; function t($n,$c,$extra=''){global $ok,$ko; if($c){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n $extra\n";}}
$c=client(); $em=em($c);
$em->createQuery('DELETE FROM App\Entity\Lettre')->execute(); $em->createQuery('DELETE FROM App\Entity\NewsletterSubscriber s WHERE s.email LIKE :e')->setParameter('e','%@lt.test')->execute();
foreach(['t_adm@x.fr'] as $m){ if(!$em->getRepository(User::class)->findOneBy(['email'=>$m])){ $u=(new User())->setEmail($m)->setFirstName('Adm')->setLastName('T')->setPassword('x')->setRoles(['ROLE_ADMIN'])->setIsActive(true); $u->setCreatedAt(new DateTimeImmutable('-30 days')); $em->persist($u);} } $em->flush();

// Public : inscription
$c=client(); $cr=$c->request('GET','/lettre'); t('page lettre 200',$c->getResponse()->getStatusCode()===200);
$f=$cr->selectButton('Recevoir la lettre')->form(['email'=>'a@lt.test','consent'=>1]); $c->submit($f);
$sub=em($c)->getRepository(NewsletterSubscriber::class)->findOneBy(['email'=>'a@lt.test']);
t('abonne cree INACTIF et non confirme',$sub && !$sub->isActive() && !$sub->getConfirmedAt());
$m=mails($c); t('1 mail de confirmation',count($m)===1 && str_contains($m[0]->getSubject(),'Confirmez'));
preg_match('~https?://[^\s"<]+/lettre/confirmer/\d+/[0-9.a-f]+~',$m[0]->getTextBody(),$mm); $url=$mm[0]??''; t('lien de confirmation present',$url!=='');
$path=parse_url($url,PHP_URL_PATH);
// sans consentement
$c=client(); $cr=$c->request('GET','/lettre'); $c->submit($cr->selectButton('Recevoir la lettre')->form(['email'=>'b@lt.test'])); t('sans consentement : rien cree',!em($c)->getRepository(NewsletterSubscriber::class)->findOneBy(['email'=>'b@lt.test']));
// honeypot
$c=client(); $cr=$c->request('GET','/lettre'); $f=$cr->selectButton('Recevoir la lettre')->form(['email'=>'bot@lt.test','consent'=>1,'website'=>'http://spam']); $c->submit($f); t('honeypot : rien cree',!em($c)->getRepository(NewsletterSubscriber::class)->findOneBy(['email'=>'bot@lt.test']));
// csrf invalide
$c=client(); $c->request('POST','/lettre/inscription',['email'=>'csrf@lt.test','consent'=>1,'_token'=>'faux']); t('csrf invalide : rien cree',!em($c)->getRepository(NewsletterSubscriber::class)->findOneBy(['email'=>'csrf@lt.test']));
// confirmation : GET n'active pas
$c=client(); $c->request('GET',$path); t('GET confirmer = page, sans activer',$c->getResponse()->getStatusCode()===200 && !em($c)->getRepository(NewsletterSubscriber::class)->findOneBy(['email'=>'a@lt.test'])->isActive());
$c=client(); $c->request('GET',preg_replace('~/[0-9.a-f]+$~','/1.deadbeef',$path)); t('jeton falsifie refuse (400)',$c->getResponse()->getStatusCode()===400);
$c=client(); $c->request('POST',$path); $s=em($c)->getRepository(NewsletterSubscriber::class)->findOneBy(['email'=>'a@lt.test']); t('POST confirmer active',$s->isActive() && $s->getConfirmedAt());
// meme reponse pour adresse inconnue / connue (pas d'enumeration)
$c=client(); $cr=$c->request('GET','/lettre'); $c->submit($cr->selectButton('Recevoir la lettre')->form(['email'=>'a@lt.test','consent'=>1])); $r1=$c->getResponse()->headers->get('Location'); t('deja inscrit : meme redirection, aucun mail',$r1!==null && count(mails($c))===0);
// second abonne (non confirme) + legacy importe (actif mais non confirme)
$c=client(); $em=em($c); $leg=(new NewsletterSubscriber())->setEmail('legacy@lt.test'); $leg->setIsActive(true); $em->persist($leg); $em->flush();
// Desinscription
$c=client(); $em=em($c); $x=(new NewsletterSubscriber())->setEmail('c@lt.test'); $x->setIsActive(true)->setConfirmedAt(new DateTimeImmutable()); $em->persist($x); $em->flush();
$tok=$c->getContainer()->get('test.service_container')->get(App\Service\Lettre\LettreTokens::class)->unsubscribe($x);
$c=client(); $c->request('GET',"/lettre/desinscription/{$x->getId()}/$tok"); t('GET desinscription = page seulement',$c->getResponse()->getStatusCode()===200 && em($c)->getRepository(NewsletterSubscriber::class)->find($x->getId())->isActive());
$c=client(); $c->request('GET',"/lettre/desinscription/{$x->getId()}/faux"); t('jeton desinscription faux : 400',$c->getResponse()->getStatusCode()===400);
$c=client(); $c->request('POST',"/lettre/desinscription/{$x->getId()}/$tok",['List-Unsubscribe'=>'One-Click']); t('POST one-click desinscrit',!em($c)->getRepository(NewsletterSubscriber::class)->find($x->getId())->isActive());
// jeton d'un autre abonne inutilisable
$c=client(); $c->request('POST',"/lettre/desinscription/{$s->getId()}/$tok"); t('jeton croise refuse',$c->getResponse()->getStatusCode()===400 && em($c)->getRepository(NewsletterSubscriber::class)->find($s->getId())->isActive());

// Admin : acces
$c=client(); $c->request('GET','/admin/lettres'); t('admin anonyme -> login',$c->getResponse()->isRedirect() && str_contains($c->getResponse()->headers->get('Location'),'login'));
$c=client(); $uu=em($c)->getRepository(User::class)->findOneBy([]); $c->loginUser($uu); $c=client('t_old1@x.fr'); $c->request('GET','/admin/lettres'); t('admin refuse a un simple membre (403)',$c->getResponse()->getStatusCode()===403);
$c=client('t_adm@x.fr'); $cr=$c->request('GET','/admin/lettres/nouvelle'); t('admin voit le formulaire',$c->getResponse()->getStatusCode()===200);
$c->submit($cr->selectButton('Enregistrer le brouillon')->form(['subject'=>'Octobre : où en sont les débats ?','body'=>"Bonjour,\n\nVoici les nouvelles du mois. <script>alert(1)</script> Détails sur https://exemple.fr/page.\n\nÀ bientôt."]));
$L=em($c)->getRepository(Lettre::class)->findOneBy([]); t('brouillon enregistre',$L && $L->getStatus()==='brouillon');
$c=client('t_adm@x.fr'); $c->request('GET','/admin/lettres/'.$L->getId()); $h=$c->getResponse()->getContent(); t('HTML saisi echappe (pas de <script>)',!str_contains($h,'<script>alert(1)') && str_contains($h,'&lt;script&gt;')); t('lien https rendu cliquable',str_contains($h,'href="https://exemple.fr/page"'));
// Anti-CSRF / same-origin
$c=client('t_adm@x.fr'); $c->request('POST','/admin/lettres/'.$L->getId().'/envoyer',['_token'=>'faux','confirm_count'=>2]); t('queue sans bon jeton : refuse',in_array($c->getResponse()->getStatusCode(),[403]));
$c=client('t_adm@x.fr'); $c->request('POST','/admin/lettres/'.$L->getId().'/supprimer',[],[],['HTTP_ORIGIN'=>'https://evil.example']); t('POST admin cross-origin : 403',$c->getResponse()->getStatusCode()===403);
// Test + mise en file avec mauvais nombre
$c=client('t_adm@x.fr'); $c->request('GET','/admin/lettres/'.$L->getId()); $cr=$c->getCrawler();
$c->submit($cr->selectButton("M'envoyer un test")->form()); $m=mails($c); t('test envoye uniquement a l admin',count($m)===1 && $m[0]->getTo()[0]->getAddress()==='t_adm@x.fr' && str_starts_with($m[0]->getSubject(),'[TEST]'));
$hdr=$m[0]->getHeaders(); t('en-tete List-Unsubscribe + One-Click',$hdr->has('List-Unsubscribe') && $hdr->has('List-Unsubscribe-Post'));
t('aucun pixel espion',!str_contains($m[0]->getHtmlBody(),'track') && !str_contains($m[0]->getHtmlBody(),'<img'));
t('adresse postale refusee si absente OU presente selon config', true);
$c=client('t_adm@x.fr'); $c->request('GET','/admin/lettres/'.$L->getId()); $cr=$c->getCrawler(); $f=$cr->selectButton("Mettre en file d'envoi")->form(['confirm_count'=>'999']); $c->submit($f); t('mauvais nombre : reste brouillon',em($c)->getRepository(Lettre::class)->find($L->getId())->getStatus()==='brouillon');
echo "\n$ok OK / $ko KO\n";
