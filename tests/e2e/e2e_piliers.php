<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\Mission;
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$t1='ET SI ON DONNAIT ENFIN LA PAROLE AUX CITOYENS ? '.uniqid();$t2='Inactif '.uniqid();
foreach([[$t1,true],[$t2,false]] as [$t,$a]){$m=new Mission();$m->setTitre($t)->setDescription('Description')->setType('action')->setOrdreAffichage(0)->setIsActive($a);$em->persist($m);}
try{$em->flush();}catch(\Throwable $e){echo $e->getMessage(),"\n";}
$c->request('GET','/');$h=$c->getResponse()->getContent();
t('accueil 200',$c->getResponse()->getStatusCode()==200);
t('action active affichee',str_contains($h,'Piliers et actions pour la paix')&&str_contains($h,$t1));
t('action inactive masquee',!str_contains($h,$t2));
echo "\n$ok OK / $ko KO\n";
