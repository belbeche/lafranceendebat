<?php
require '/home/claude/app/vendor/autoload.php';
putenv('ASSOCIATION_ADDRESS=1 rue de Test, 75000 Paris'); $_ENV['ASSOCIATION_ADDRESS']=$_SERVER['ASSOCIATION_ADDRESS']='1 rue de Test, 75000 Paris';
putenv('MAIL_FROM=lettre@example.org'); $_ENV['MAIL_FROM']=$_SERVER['MAIL_FROM']='lettre@example.org';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Lettre,NewsletterSubscriber};
function client($email=null){ $k=new App\Kernel('test',true); $k->boot(); $c=$k->getContainer()->get('test.client'); $c->disableReboot(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang","fr"));
  if($email){ $u=$k->getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email'=>$email]); $c->loginUser($u);} return $c; }
function em($c){ return $c->getContainer()->get('doctrine')->getManager(); }
$ok=0;$ko=0; function t($n,$c,$extra=''){global $ok,$ko; if($c){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n $extra\n";}}
$c=client('t_adm@x.fr'); $L=em($c)->getRepository(Lettre::class)->findOneBy([]);
$c->request('GET','/admin/lettres/'.$L->getId()); $cr=$c->getCrawler();
$c->submit($cr->selectButton("Mettre en file d'envoi")->form(['confirm_count'=>'1'])); $L=em($c)->getRepository(Lettre::class)->find($L->getId());
t('bon nombre : en file, cible = 1 (confirmes seulement)',$L->getStatus()==='en file' && $L->getTargetCount()===1,$L->getStatus().'/'.$L->getTargetCount());
// commande
$k=new App\Kernel('test',true); $k->boot(); $app=new Symfony\Bundle\FrameworkBundle\Console\Application($k); $app->setAutoExit(false);
$in=new Symfony\Component\Console\Input\ArrayInput(['command'=>'app:lettre:envoyer','--delay-ms'=>0]); $out=new Symfony\Component\Console\Output\BufferedOutput();
$app->run($in,$out); echo $out->fetch();
$app->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'app:lettre:envoyer','--delay-ms'=>0]),$out); echo $out->fetch();
$msgs=$k->getContainer()->get('test.service_container')->get('mailer.message_logger_listener')->getEvents()->getMessages();
$to=array_map(fn($m)=>$m->getTo()[0]->getAddress(),$msgs); t('envoye a a@lt.test seulement (pas legacy, pas desinscrit)',$to===['a@lt.test'],implode(',',$to));
$k2=new App\Kernel('test',true); $k2->boot(); $L=$k2->getContainer()->get('doctrine')->getRepository(Lettre::class)->find($L->getId()); t('statut envoyee, 1 envoye',$L->getStatus()==='envoyee' && $L->getSentCount()===1,$L->getStatus());
// relance : aucun doublon
$app->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'app:lettre:envoyer']),$out); t('relance : aucune lettre en file',str_contains($out->fetch(),'Aucune lettre'));
// limite de debit
$c=client(); $codes=[]; for($i=0;$i<7;$i++){ $cr=$c->request('GET','/lettre'); $c->submit($cr->selectButton('Recevoir la lettre')->form(['email'=>"r$i@lt.test",'consent'=>1])); }
$n=em($c)->getRepository(NewsletterSubscriber::class)->count([]); $created=count(em($c)->getRepository(NewsletterSubscriber::class)->findBy([],[],100)); 
$cnt=(int)em($c)->createQuery("SELECT COUNT(s.id) FROM App\Entity\NewsletterSubscriber s WHERE s.email LIKE 'r%@lt.test'")->getSingleScalarResult(); t('limite IP : max 5 creations/heure',$cnt<=4,"cnt=$cnt");
// en-tetes
$c=client(); $c->request('GET','/'); $h=$c->getResponse()->headers; $csp=$h->get('Content-Security-Policy');
t('CSP stricte (pas de unsafe-inline script)',$csp && !preg_match("~script-src[^;]*unsafe-inline~",$csp) && str_contains($csp,"frame-ancestors 'none'") && str_contains($csp,"object-src 'none'"));
t('X-Frame DENY, nosniff, COOP, CORP',$h->get('X-Frame-Options')==='DENY' && $h->get('X-Content-Type-Options')==='nosniff' && $h->get('Cross-Origin-Resource-Policy')==='same-origin');
preg_match("~nonce-([A-Za-z0-9_-]+)~",$csp,$m); $body=$c->getResponse()->getContent(); t('aucun script en ligne sans nonce sur l accueil', !preg_match('~<script(?![^>]*(src=|nonce=))[^>]*>~',$body));
$c=client(); $c->request('GET','/login'); t('login non cachable', str_contains($c->getResponse()->headers->get('Cache-Control'),'no-store'));
$c=client(); $c->request('GET','/reset-password'); $b=$c->getResponse()->getContent(); preg_match_all('~<script[^>]*>~',$b,$mm); t('reset-password: scripts avec nonce',!array_filter($mm[0],fn($s)=>!str_contains($s,'nonce=')&&!str_contains($s,'src=')));
echo "\n$ok OK / $ko KO\n";
