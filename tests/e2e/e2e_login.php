<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\User;
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$PW='T-'.bin2hex(random_bytes(9)); // mot de passe jetable genere a chaque test, jamais un vrai secret
$em=$c->getContainer()->get('doctrine')->getManager();$h=$c->getContainer()->get('security.user_password_hasher');
$e='login'.uniqid().'@test.fr';$u=new User();$u->setEmail($e);$u->setPassword($h->hashPassword($u,$PW));
foreach(['setFirstName','setLastName'] as $m) if(method_exists($u,$m)) $u->$m('T');
$em->persist($u);$em->flush();
function login($c,$e,$p,$tok=true){$c->request('GET','/login');$cr=$c->getCrawler();$t=$cr->filter('input[name=_csrf_token]')->attr('value');$c->request('POST','/login',['_username'=>$e,'_password'=>$p,'_csrf_token'=>$tok?$t:'bad']);return $c->getResponse();}
$r=login($c,$e,$PW);t('connexion ok redirige',$r->isRedirect()&&!str_contains($r->headers->get('Location'),'login'));
$c->request('GET','/profil');t('profil accessible apres login',$c->getResponse()->getStatusCode()==200);
$c->getCookieJar()->clear();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$r=login($c,$e,'mauvais');$c->followRedirect();t('mauvais mdp message generique',str_contains($c->getResponse()->getContent(),'incorrect'));
$c->getCookieJar()->clear();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$r=login($c,$e,$PW,false);$c->followRedirect();t('csrf invalide message explicite',str_contains($c->getResponse()->getContent(),'expiré'));
echo "$ok ok / $ko ko\n";
