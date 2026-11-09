<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,ActorContact,Lettre};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$PW='T-'.bin2hex(random_bytes(9));
$em=$c->getContainer()->get('doctrine')->getManager();
$h=$c->getContainer()->get('test.service_container')->get('security.user_password_hasher');
$u=new User();$u->setEmail('sd'.uniqid().'@t.fr');$u->setFirstName('S');$u->setLastName('D');$u->setPassword($h->hashPassword($u,$PW));$em->persist($u);$em->flush();$id=$u->getId();
$login=function($client,$ua) use($u,$PW){$client->setServerParameter('HTTP_USER_AGENT',$ua);$client->request('GET','/login');preg_match('/name="_csrf_token" value="([^"]+)"/',$client->getResponse()->getContent(),$m);$client->request('POST','/login',['_username'=>$u->getEmail(),'_password'=>$PW,'_csrf_token'=>$m[1]??'']);};
$ml=$k->getContainer()->get('test.service_container')->get('mailer.message_logger_listener');
$login($c,'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537');
$mails=$ml->getEvents()->getMessages();
t('1re connexion: pas d alerte',count($mails)===0);
// 2e appareil, autre client = autre session
$c2=new Symfony\Bundle\FrameworkBundle\KernelBrowser($k);$c2->disableReboot();
$c2->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$login($c2,'Mozilla/5.0 (iPhone) Safari/604');
$mails=$ml->getEvents()->getMessages();
t('nouvel appareil: alerte e-mail',count($mails)===1&&str_contains($mails[0]->getSubject(),'Nouvelle connexion'));
$c2->request('GET','/mon-espace');t('appareil 2 connecte',$c2->getResponse()->getStatusCode()==200);
// revocation depuis appareil 1
sleep(1);
$c->request('GET','/profil/connexions');preg_match_all('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST','/profil/connexions/deconnecter-autres',['_token'=>end($m[1])]);
$c->request('GET','/mon-espace');t('appareil 1 reste connecte',$c->getResponse()->getStatusCode()==200);
$c2->request('GET','/mon-espace');t('appareil 2 deconnecte',$c2->getResponse()->isRedirect()&&str_contains($c2->getResponse()->headers->get('Location'),'login'));
// ciblage
$mk=function($email,$cat,$cp) use($em){$a=new ActorContact($email,'Org '.$email,'https://exemple.fr/x');$a->setCategory($cat)->setCoordinates(null,null,$cp,'V',null);$em->persist($a);};
$q=uniqid();$mk("a$q@mairie27.fr",'commune','27000');$mk("b$q@mairie70.fr",'commune','70000');$mk("c$q@dep27.fr",'departement','27100');$em->flush();
$r=$em->getRepository(ActorContact::class);
$all=$r->countEligible(true);
$c27=$r->countEligible(true,null,'commune','27');$d27=$r->countEligible(true,null,null,'27');$cc=$r->countEligible(true,null,'commune',null);
t('cible commune+27 = 1 de plus que base',$c27>=1&&$c27<$all);
t('cible dept 27 >= commune 27 + 1',$d27>=$c27+1);
t('cible commune seule >= commune+27',$cc>=$c27+1);
$l=new Lettre();$l->setTarget('commune','27')->setAudience('invitation');
t('lettre garde le ciblage',$l->getActorCategory()==='commune'&&$l->getActorDept()==='27');
$l->setTarget('nimporte','abc');t('ciblage invalide ignore',$l->getActorCategory()===null&&$l->getActorDept()===null);
echo "\n$ok OK / $ko KO\n";
