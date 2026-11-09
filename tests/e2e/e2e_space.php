<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\User;
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$u=new User();$u->setEmail('sp'.uniqid().'@t.fr');$u->setPassword('x');$u->setFirstName('Camille');$u->setLastName('D');$u->setRoles(['ROLE_USER','ROLE_ACTOR']);$em->persist($u);$em->flush();
$c->request('GET','/');t('accueil anonyme revalide',preg_match('/no-cache|no-store/',(string)$c->getResponse()->headers->get('Cache-Control')));
$c->loginUser($u);
$c->request('GET','/mon-espace');$h=$c->getResponse()->getContent();t('hub 200',$c->getResponse()->getStatusCode()==200);t('hub sidebar',str_contains($h,'sp-nav')&&str_contains($h,'Que voulez-vous faire'));
$c->request('GET','/espace-acteur');$h=$c->getResponse()->getContent();t('acteur 200',$c->getResponse()->getStatusCode()==200);t('acteur sidebar',str_contains($h,'sp-nav')&&str_contains($h,'Commandes reçues'));
$c->request('GET','/espace/association');t('role space repond',in_array($c->getResponse()->getStatusCode(),[200,302,403,404]));
echo "\n$ok OK / $ko KO\n";
