<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
foreach(['/','/classement','/classement?periode=tout'] as $u){$c->request('GET',$u);echo $u,' ',$c->getResponse()->getStatusCode(),"\n";}
$c->request('GET','/classement');echo str_contains($c->getResponse()->getContent(),'Pourquoi figurer')?"OK perks\n":"KO perks\n";
$em=$c->getContainer()->get('doctrine')->getManager();$u=new App\Entity\User();$u->setEmail('rk'.uniqid().'@orga-test.fr')->setPassword('x')->setRoles(['ROLE_USER','ROLE_ELU']);$u->setFirstName('R');$u->setLastName('K');$em->persist($u);$em->flush();
$c->loginUser($u);$c->request('GET','/classement');echo str_contains($c->getResponse()->getContent(),'Votre progression')?"OK progression\n":"KO progression ".$c->getResponse()->getStatusCode()."\n";
$c->request('GET','/espace-acteur/candidatures');echo $c->getResponse()->getStatusCode()."\n";
