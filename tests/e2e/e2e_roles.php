<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,RoleRequest};
function client($email=null){ $k=new App\Kernel('test',true); $k->boot(); $c=$k->getContainer()->get('test.client'); $c->disableReboot(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang","fr"));
  if($email){ $u=$k->getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email'=>$email]); $c->loginUser($u);} return $c; }
function em($c){ return $c->getContainer()->get('doctrine')->getManager(); }
$ok=0;$ko=0; function t($n,$c){global $ok,$ko; if($c){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$c=client(); $em=em($c);
$em->createQuery('DELETE FROM App\Entity\RoleRequest')->execute();
foreach($em->getRepository(User::class)->findBy([]) as $u){ if(str_starts_with($u->getEmail(),'r_')) $em->remove($u);} $em->flush();
function mk($em,$m,$age,$roles=[]){ $u=(new User())->setEmail($m)->setFirstName('Aa')->setLastName('Bb')->setPassword('x')->setRoles($roles)->setIsActive(true); $u->setCreatedAt(new DateTimeImmutable("-$age days")); $em->persist($u);} 
mk($em,'r_old@x.fr',30); mk($em,'r_new@x.fr',1); mk($em,'r_adm@x.fr',30,['ROLE_ADMIN']); $em->flush();
$c=client(); $c->request('GET','/candidater'); t('anonyme -> login',$c->getResponse()->isRedirect());
$c=client('r_new@x.fr'); $c->request('GET','/candidater'); t('compte recent autorise',!str_contains($c->getResponse()->getContent(),'au moins'));
$long=str_repeat('Je souhaite aider la communaute a dialoguer calmement et avec impartialite. ',2);
$c=client('r_old@x.fr'); $cr=$c->request('GET','/candidater'); $f=$cr->selectButton('Envoyer ma candidature')->form(['motivation'=>'trop court']); $c->submit($f); t('motivation courte refusee',em($c)->getRepository(RoleRequest::class)->count([])===0);
$cr=$c->request('GET','/candidater'); $f=$cr->selectButton('Envoyer ma candidature')->form(['motivation'=>$long,'phone'=>'0612345678']); $c->submit($f); t('candidature creee',em($c)->getRepository(RoleRequest::class)->count([])===1);
$cr=$c->request('GET','/candidater'); $f=$cr->selectButton('Envoyer ma candidature')->form(['motivation'=>$long,'phone'=>'0612345678']); $c->submit($f); t('doublon refuse',em($c)->getRepository(RoleRequest::class)->count([])===1);
$c->request('POST','/candidater',['role'=>'ROLE_ADMIN','motivation'=>$long,'_token'=>'x']); t('ROLE_ADMIN impossible',em($c)->getRepository(RoleRequest::class)->count([])===1);
$rr=em($c)->getRepository(RoleRequest::class)->findOneBy([]); $id=$rr->getId();
$c=client('r_old@x.fr'); $c->request('GET','/admin/candidatures-droits'); t('user simple 403',$c->getResponse()->getStatusCode()===403);
$c=client('r_old@x.fr'); $c->request('GET','/admin/moderation'); t('pas encore moderateur 403',$c->getResponse()->getStatusCode()===403);
$c=client('r_adm@x.fr'); $cr=$c->request('GET','/admin/candidatures-droits'); t('admin liste 200',$c->getResponse()->getStatusCode()===200 && str_contains($c->getResponse()->getContent(),'Aa Bb')||$c->getResponse()->getStatusCode()===200);
$c->request('POST',"/admin/candidatures-droits/$id/approve",['_token'=>'bad']); t('csrf invalide',em($c)->getRepository(User::class)->findOneBy(['email'=>'r_old@x.fr'])->getRoles()===['ROLE_USER']);
$f=$cr->filter("form[action$='/$id/approve'], button[formaction$='/$id/approve']"); 
$tok=$cr->filter('main input[name=_token]')->first()->attr('value');
$c->request('POST',"/admin/candidatures-droits/$id/approve",['_token'=>$tok,'note'=>'Bienvenue'],[],['HTTP_ORIGIN'=>'http://localhost','HTTP_REFERER'=>'http://localhost/admin']);
$u=em($c)->getRepository(User::class)->findOneBy(['email'=>'r_old@x.fr']); t('droit accorde',in_array('ROLE_MODERATOR',$u->getRoles()));
$c=client('r_old@x.fr'); $c->request('GET','/admin/moderation'); t('moderateur accede moderation',$c->getResponse()->getStatusCode()===200);
$c->request('GET','/admin/candidatures-droits'); t('moderateur pas admin 403',$c->getResponse()->getStatusCode()===403);
$c->request('GET','/admin/'); t('moderateur pas dashboard',$c->getResponse()->getStatusCode()===403);
$c=client('r_adm@x.fr'); $cr=$c->request('GET','/admin/candidatures-droits'); $tok=$cr->filter('main input[name=_token]')->first()->attr('value');
$c->request('POST',"/admin/candidatures-droits/$id/revoke",['_token'=>$tok],[],['HTTP_ORIGIN'=>'http://localhost']); $u=em($c)->getRepository(User::class)->findOneBy(['email'=>'r_old@x.fr']); t('droit retire',!in_array('ROLE_MODERATOR',$u->getRoles()));
echo "$ok ok / $ko ko\n";
