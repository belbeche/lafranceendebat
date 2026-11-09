<?php
require '/home/claude/app/vendor/autoload.php';
$_SERVER['APP_ENV']='test';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
$k=new App\Kernel('test',true);$k->boot();
$c=$k->getContainer()->get('test.client');
$ok=fn($n,$b)=>print(($b?'OK ':'KO ').$n."\n");
$c->request('GET','/');$ok('banner on home',str_contains($c->getResponse()->getContent(),'welcome-banner'));
$ok('commons home',str_contains($c->getResponse()->getContent(),'commons-note'));
foreach(['/bienvenue','/bienvenue/citoyen','/bienvenue/contribuable','/bienvenue/association','/bienvenue/entreprise','/bienvenue/elu','/bienvenue/expert','/a-propos','/inscription'] as $u){$c->request('GET',$u);$ok($u.' '.$c->getResponse()->getStatusCode(),$c->getResponse()->getStatusCode()==200);}
$c->request('GET','/bienvenue/plus-tard');$ok('later redirect',$c->getResponse()->isRedirect());
$ok('cookie set',(bool)$c->getResponse()->headers->getCookies());
$c->request('GET','/');$ok('footer notice',str_contains($c->getResponse()->getContent(),'appartient'));
$c->request("GET","/");$h=$c->getResponse()->getContent();$i=strpos($h,"footer-debat");echo substr(strip_tags(substr($h,$i,1500)),0,500),"\n";
