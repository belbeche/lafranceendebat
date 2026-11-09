<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$em=$c->getContainer()->get('doctrine')->getManager();
function ok($n,$x){echo ($x?'OK  ':'KO  ').$n."\n";}
$c->request('GET','/');ok('visiteur: creer compte',str_contains($c->getResponse()->getContent(),'Créez votre compte gratuit'));
$u=new App\Entity\User();$u->setEmail('ns'.uniqid().'@orga-test.fr')->setPassword('x')->setRoles(['ROLE_USER']);$u->setFirstName('N');$u->setLastName('S');$em->persist($u);$em->flush();
$c->loginUser($u);$c->request('GET','/');ok('citoyen neuf: soutenir',str_contains($c->getResponse()->getContent(),'Soutenez une première proposition'));
$c->request('GET','/propositions');echo $c->getResponse()->getStatusCode(),"\n";ok('page propositions ok',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'needFinder'));
$h=$c->getResponse()->getContent();echo substr_count($h,'needFinder'),' ',substr_count($h,'Que voulez'),' ',strlen($h),"\n";
