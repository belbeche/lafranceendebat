<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,RoleRequest};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$u=new User();$u->setEmail('rr'.uniqid().'@orga-test.fr')->setPassword('x')->setRoles(['ROLE_USER']);$u->setFirstName('A');$u->setLastName('B');$em->persist($u);$em->flush();
$c->loginUser($u);$c->request('GET','/candidater?role=ROLE_ELU');$h=$c->getResponse()->getContent();
t('perks affiches',str_contains($h,'Ce que ce droit vous permet')&&str_contains($h,'proof_url'));
preg_match('/name="_token" value="([^"]+)"/',$h,$m);$mot=str_repeat('Je souhaite répondre aux habitants de ma commune sur la santé. ',2);
$c->request('POST','/candidater',['_token'=>$m[1],'role'=>'ROLE_ELU','organisation'=>'Mairie X','proof_url'=>'http://x','phone'=>'0612345678','motivation'=>$mot]);t('preuve http refusee',!$em->getRepository(RoleRequest::class)->findOneBy(['user'=>$u]));
$c->request('POST','/candidater',['_token'=>$m[1],'role'=>'ROLE_ELU','organisation'=>'Mairie X','proof_url'=>'https://www.mairie-x.fr/conseil','territory'=>'Calvados','phone'=>'0612345678','motivation'=>$mot]);
preg_match_all("/alert-danger\">([^<]+)/",$c->getResponse()->getContent(),$er);print_r($er[1]);echo $c->getResponse()->getStatusCode(),' ',substr(strip_tags($c->getResponse()->getContent()),0,200),"\n";$r=$em->getRepository(RoleRequest::class)->findOneBy(['user'=>$u]);$em->clear();$u=$em->getRepository(User::class)->findOneBy(['email'=>$u->getEmail()]);t('demande creee avec details',$r&&$r->getOrganisation()==='Mairie X'&&$r->getProofUrl()&&$u->getPhone()==='0612345678');
$a=new User();$a->setEmail('ad'.uniqid().'@orga-test.fr')->setPassword('x')->setRoles(['ROLE_ADMIN']);$a->setFirstName('Ad');$a->setLastName('M');$em->persist($a);$em->flush();$c->loginUser($a);
$c->request('GET','/admin/candidatures-droits');$s=$c->getResponse()->getStatusCode();t('admin voit preuve '.$s,$s==200&&str_contains($c->getResponse()->getContent(),'mairie-x.fr'));
echo "$ok ok / $ko ko\n";
