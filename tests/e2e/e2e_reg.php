<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\User;
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
function reg($c,$em,$profile,$org,$ageOk=true){
 $c->request('GET','/inscription?profil='.$profile);$h=$c->getResponse()->getContent();
 preg_match('/name="registration_form\[_token\]" value="([^"]+)"/',$h,$m);
 $e=$profile.uniqid().'@orga-test.fr';
 $d=['_token'=>$m[1]??'','profile'=>$profile,'organisation'=>$org,'email'=>$e,'firstName'=>'Jean','lastName'=>'Test','phone'=>'0612345678','postalCode'=>'75001','plainPassword'=>'T-'.bin2hex(random_bytes(9)),'agreeCharter'=>'1'];
 if($ageOk)$d['ageOk']='1';
 $c->request('POST','/inscription',['registration_form'=>$d]);return [$e,$c->getResponse()];}
$c->request('GET','/inscription');t('page 200 avec profils',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Élu ou institution'));
[$e,$r]=reg($c,$em,'citoyen','');t('citoyen redirige bienvenue',$r->isRedirect()&&str_contains($r->headers->get('Location'),'/bienvenue/citoyen'));
$u=$em->getRepository(User::class)->findOneBy(['email'=>$e]);t('citoyen cree sans droits acteur',$u&&$u->getRoles()===['ROLE_USER']&&$u->getPhone());
$c->getCookieJar()->clear();[$e,$r]=reg($c,$em,'elu','');t('elu sans organisation refuse',$r->getStatusCode()==422&&!$em->getRepository(User::class)->findOneBy(['email'=>$e]));
$c->getCookieJar()->clear();[$e,$r]=reg($c,$em,'elu','Mairie de Test');t('elu redirige vers demande de droits',$r->isRedirect()&&str_contains($r->headers->get('Location'),'role=ROLE_ELU'));
$u=$em->getRepository(User::class)->findOneBy(['email'=>$e]);t('elu pas de droit auto',$u&&!in_array('ROLE_ELU',$u->getRoles()));
$c->getCookieJar()->clear();[$e,$r]=reg($c,$em,'citoyen','',false);t('sans age refuse',$r->getStatusCode()==422);
echo "$ok ok / $ko ko\n";
