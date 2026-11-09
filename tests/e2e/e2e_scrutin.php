<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Consultation,Ballot};
function client($email=null){ $k=new App\Kernel('test',true); $k->boot(); $c=$k->getContainer()->get('test.client'); $c->disableReboot(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang","fr"));
  if($email){ $u=$k->getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email'=>$email]); $c->loginUser($u);} return $c; }
function em($c){ return $c->getContainer()->get('doctrine')->getManager(); }
$ok=0;$ko=0; function t($n,$c){global $ok,$ko; if($c){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$c=client(); $em=em($c);
$em->createQuery('DELETE FROM App\Entity\Ballot')->execute(); $em->createQuery('DELETE FROM App\Entity\Consultation')->execute();
foreach($em->getRepository(User::class)->findBy([]) as $u){ if(str_starts_with($u->getEmail(),'s_')) $em->remove($u);} $em->flush();
foreach([['s_u1@x.fr',[]],['s_u2@x.fr',[]],['s_adm@x.fr',['ROLE_ADMIN']]] as [$m,$r]){ $u=(new User())->setEmail($m)->setFirstName('Aa')->setLastName('Bb')->setPassword('x')->setRoles($r)->setIsActive(true); $em->persist($u);} $em->flush();
$c=client(); $c->request('GET','/scrutins'); t('liste publique',$c->getResponse()->getStatusCode()===200);
$c->request('GET','/admin/scrutins/nouveau'); t('admin anonyme refuse',$c->getResponse()->isRedirect()||$c->getResponse()->getStatusCode()>=401);
$c=client('s_u1@x.fr'); $c->request('GET','/admin/scrutins/nouveau'); t('user simple 403',$c->getResponse()->getStatusCode()===403);
$c=client('s_adm@x.fr'); $cr=$c->request('GET','/admin/scrutins/nouveau'); t('admin form 200',$c->getResponse()->getStatusCode()===200);
$f=$cr->selectButton('Publier le scrutin')->form(['question'=>'Faut-il plus de debats locaux ?','options'=>"Oui\nNon\nOui",'closes'=>date('Y-m-d',strtotime('+5 days'))]); $c->submit($f);
t('doublons dedupliques acceptes (2 options)',$c->getResponse()->isRedirect()); $em=em($c); $em->createQuery('DELETE FROM App\Entity\Consultation')->execute();
$cr=$c->request('GET','/admin/scrutins/nouveau');
$f=$cr->selectButton('Publier le scrutin')->form(['question'=>'Faut-il plus de debats locaux ?','options'=>"Oui\nNon\nPeut-etre",'closes'=>date('Y-m-d',strtotime('+5 days'))]); $c->submit($f);
t('creation redirige',$c->getResponse()->isRedirect());
$cons=em($c)->getRepository(Consultation::class)->findOneBy([]); $id=$cons->getId(); t('scrutin en base',$cons&&count($cons->getOptions())===3);
$c=client('s_u1@x.fr'); $cr=$c->request('GET',"/scrutins/$id"); t('page 200 sans resultats',$c->getResponse()->getStatusCode()===200 && !str_contains($c->getResponse()->getContent(),'(votre avis)') && str_contains($c->getResponse()->getContent(),'Donner mon avis'));
$f=$cr->selectButton('Donner mon avis')->form(); $f['choice']->select('1'); $c->submit($f);
t('vote ok',em($c)->getRepository(Ballot::class)->count([])===1);
$c->followRedirect(); t('resultats apres vote',str_contains($c->getResponse()->getContent(),'(votre avis)'));
$c->request('POST',"/scrutins/$id/voter",['choice'=>0,'_token'=>'bad']); t('csrf invalide refuse',em($c)->getRepository(Ballot::class)->count([])===1);
$c2=client(); $c2->request('POST',"/scrutins/$id/voter",['choice'=>0]); t('anonyme vote -> login',$c2->getResponse()->isRedirect()&&str_contains($c2->getResponse()->headers->get('Location'),'/login'));
$c=client('s_u2@x.fr'); $cr=$c->request('GET',"/scrutins/$id"); $tok=$cr->filter('main input[name=_token]')->attr('value');
$c->request('POST',"/scrutins/$id/voter",['choice'=>9,'_token'=>$tok]); t('choix hors liste refuse',em($c)->getRepository(Ballot::class)->count([])===1);
$c->request('POST',"/scrutins/$id/voter",['choice'=>0,'_token'=>$tok]); t('u2 vote',em($c)->getRepository(Ballot::class)->count([])===2);
$c->request('POST',"/scrutins/$id/voter",['choice'=>2,'_token'=>$tok]); t('double vote refuse',em($c)->getRepository(Ballot::class)->count([])===2);
$cons=em($c)->getRepository(Consultation::class)->find($id); $cons->setClosesAt(new DateTimeImmutable('-1 hour')); em($c)->flush();
$c=client('s_u1@x.fr'); $c->request('GET',"/scrutins/$id"); t('clos: resultats visibles',str_contains($c->getResponse()->getContent(),'Résultats'));
$cons=em($c)->getRepository(Consultation::class)->find($id); $cons->setClosesAt(new DateTimeImmutable('+1 day')); $cons->setOpensAt(new DateTimeImmutable('+1 hour')); em($c)->flush();
$c=client('s_u2@x.fr'); $c->request('GET',"/scrutins");t('index 200',$c->getResponse()->getStatusCode()===200);
echo "$ok ok / $ko ko\n";
