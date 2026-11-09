<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,LoginEvent};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$PW='T-'.bin2hex(random_bytes(9)); // mot de passe jetable genere a chaque test, jamais un vrai secret
$em=$c->getContainer()->get('doctrine')->getManager();
$h=$c->getContainer()->get('test.service_container')->get('security.user_password_hasher');
$u=new User();$u->setEmail('lh'.uniqid().'@t.fr');$u->setFirstName('L');$u->setLastName('H');$u->setPassword($h->hashPassword($u,$PW));$em->persist($u);$em->flush();$id=$u->getId();
t('ip raccourcie',LoginEvent::shorten('192.168.4.27')==='192.168.4.x');
t('appareil lisible',LoginEvent::describe('Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537')==='Chrome sur Windows');
$c->request('GET','/login');preg_match('/name="_csrf_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST','/login',['_username'=>$u->getEmail(),'_password'=>'T-faux-'.bin2hex(random_bytes(4)),'_csrf_token'=>$m[1]??'']);
$em->clear();t('echec note',$em->getRepository(LoginEvent::class)->count(['user'=>$id,'success'=>false])===1);
$c->request('GET','/login');preg_match('/name="_csrf_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST','/login',['_username'=>$u->getEmail(),'_password'=>$PW,'_csrf_token'=>$m[1]??'']);
$em->clear();$u=$em->getRepository(User::class)->find($id);
t('lastLogin renseigne',$u->getLastLogin()!==null);t('succes note',$em->getRepository(LoginEvent::class)->count(['user'=>$id,'success'=>true])===1);
$c->request('GET','/profil/connexions');t('page connexions',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Échec'));
$c2=$k->getContainer()->get('test.client');$c->request('GET','/login');preg_match('/name="_csrf_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST','/logout');$c->request('POST','/login',['_username'=>'x@y.fr','_password'=>'z','_csrf_token'=>$m[1]??'']);$c->followRedirect();
t('page login apres echec: 200 + message',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'incorrect'));
echo "\n$ok OK / $ko KO\n";
