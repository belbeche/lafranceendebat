<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');
$ok=fn($n,$b)=>print(($b?'OK ':'KO ').$n."\n");
foreach(['/','/missions','/tutoriels','/plan-marshall-citoyen','/propositions','/debats','/bienvenue/association','/services'] as $u){$c->request('GET',$u);$st=$c->getResponse()->getStatusCode();$h=$c->getResponse()->getContent();$ok("$u $st",in_array($st,[200,301,302])); if($st==200)$ok("  aide presente",str_contains($h,'needFinder'));}
$c->request('GET','/missions');$h=$c->getResponse()->getContent();$ok('missions plan',str_contains($h,'Diagnostic de coûts')&&str_contains($h,'Évaluation externe'));
$c->request('GET','/');$ok('home missions',str_contains($c->getResponse()->getContent(),'chacun peut contribuer, certains peuvent en vivre'));
$c->request('GET','/role-request?role=ROLE_ASSOCIATION');echo $c->getResponse()->getStatusCode(),"\n";
