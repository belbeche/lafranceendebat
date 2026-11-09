<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Inspiration};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$c->request('GET','/ils-ont-contribue');$h=$c->getResponse()->getContent();
t('page 200',$c->getResponse()->getStatusCode()==200);
t('Michèle Guerrin visible',str_contains($h,'Michèle Guerrin'));
$c->request('GET','/ils-ont-contribue?domaine=sante');$h=$c->getResponse()->getContent();
t('filtre sante: Pasteur oui, Curie non',str_contains($h,'Louis Pasteur')&&!str_contains($h,'Marie Curie'));
$c->request('GET','/ils-ont-contribue?q=Nobel');$h=$c->getResponse()->getContent();
t('recherche Nobel',str_contains($h,'Marie Curie')&&str_contains($h,'Elinor Ostrom')&&!str_contains($h,'Louis Pasteur'));
$c->request('GET','/ils-ont-contribue?q='.urlencode("%"));t('recherche % sans erreur',$c->getResponse()->getStatusCode()==200);
$u=new User();$u->setEmail('in'.uniqid().'@t.fr');$u->setPassword('x');$u->setFirstName('A');$u->setLastName('B');$em->persist($u);$em->flush();
$c->request('POST','/ils-ont-contribue',['name'=>'Test']);t('POST anonyme redirige login',in_array($c->getResponse()->getStatusCode(),[302,303]));
$c->loginUser($u);$c->request('GET','/ils-ont-contribue');preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$nm='Personne test '.uniqid();
$c->request('POST','/ils-ont-contribue',['_token'=>$m[1],'name'=>$nm,'domain'=>'sciences','summary'=>'Chercheuse qui a ouvert ses données et formé des centaines de jeunes à la science.','source'=>'https://exemple.fr/page']);
$i=$em->getRepository(Inspiration::class)->findOneBy(['name'=>$nm]);
t('proposition en attente',$i&&$i->getStatus()==='pending');
$c->request('GET','/ils-ont-contribue');t('non publiee invisible',!str_contains($c->getResponse()->getContent(),$nm));
$c->request('POST','/ils-ont-contribue',['_token'=>$m[1],'name'=>'Autre '.uniqid(),'domain'=>'sciences','summary'=>'Trop court','source'=>'http://pas-https.fr']);
t('refus donnees invalides',str_contains($c->getResponse()->getContent(),'source publique'));
echo "\n$ok OK / $ko KO\n";
