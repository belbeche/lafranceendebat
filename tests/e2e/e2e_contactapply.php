<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,ActorContact,ContactApplication};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
try{$em->getConnection()->executeStatement("DELETE FROM user WHERE email LIKE '%@orga-test.fr' AND roles LIKE '%JOURNALIST%'");}catch(\Throwable $e){}
$mk=function($roles,$p)use($em){$u=new User();$u->setEmail($p.uniqid().'@orga-test.fr')->setPassword('x')->setRoles($roles);$u->setFirstName($p);$u->setLastName('T');$em->persist($u);$em->flush();return $u;};
$j=$mk(['ROLE_JOURNALIST'],'jr');$me=$mk(['ROLE_USER'],'cand');
$mkc=function($n,$consent=false)use($em){$a=new ActorContact('c'.uniqid().'@mairie-test.fr','Mairie '.$n,'https://www.mairie-test.fr/contact');if($consent)$a->giveConsent();$em->persist($a);$em->flush();return $a;};
$c1=$mkc('A',true);$c2=$mkc('B');$c3=$mkc('C');$c4=$mkc('D');$un=$mkc('E');$un->unsubscribe();$em->flush();
$c->loginUser($me);
$c->request('GET','/profil-inexistant');
$c->request('GET','/inscription?profil=association');t('connecte + profil => /candidater',str_contains((string)$c->getResponse()->headers->get('Location'),'/candidater')&&str_contains((string)$c->getResponse()->headers->get('Location'),'ROLE_ASSOCIATION'));
$c->request('GET','/missions/m11/candidater');$h=$c->getResponse()->getContent();t('mission 11 page '.$c->getResponse()->getStatusCode(),$c->getResponse()->getStatusCode()==200);
t('journaliste listé',str_contains($h,'to'.$j->getId()));
t('contact consentant listé',str_contains($h,'ct'.$c1->getId())&&str_contains($h,'ct'.$c2->getId()));
t('désinscrit absent',!str_contains($h,'ct'.$un->getId()));
preg_match('/name="_token" value="([^"]+)"/',$h,$m);
$base=['_token'=>$m[1],'email'=>$me->getEmail(),'phone'=>'0612345678','message'=>str_repeat('Journaliste local, je propose de vérifier des informations sourcées. ',2)];
$c->request('POST','/missions/m11/candidater',$base+['to'=>[$j->getId()],'contacts'=>[$c1->getId()]]);t('sans partage email => refus',!$em->getRepository(ContactApplication::class)->findOneBy(['applicant'=>$me]));
$c->request('POST','/missions/m11/candidater',$base+['to'=>[$j->getId()],'contacts'=>[$c1->getId(),$c2->getId(),$c3->getId(),$c4->getId()],'share_email'=>'1']);t('4 contacts => refus',!$em->getRepository(ContactApplication::class)->findOneBy(['applicant'=>$me]));
$c->request('POST','/missions/m11/candidater',$base+['to'=>[$j->getId()],'contacts'=>[$c1->getId(),$c2->getId()],'share_email'=>'1']);
$em->clear();$n=count($em->getRepository(ContactApplication::class)->findBy(['applicant'=>$em->getRepository(User::class)->find($me->getId())]));t('2 contacts enregistrés',$n==2);
$c2=$em->getRepository(ActorContact::class)->find($c2->getId());t('contact non consentant marqué contacté+invité',$c2->getContactCount()==1&&$c2->getInvitedAt()!==null);
$c->request('GET','/mes-candidatures');t('liste mes candidatures',str_contains($c->getResponse()->getContent(),'Mairie B'));
$c->request('POST','/missions/m11/candidater',$base+['to'=>[$j->getId()],'contacts'=>[$c2->getId()],'share_email'=>'1']);$em->clear();
$c2=$em->getRepository(ActorContact::class)->find($c2->getId());t('pas de doublon',$c2->getContactCount()==1);
$c->request('GET','/missions/m11/candidater');t('non consentant déjà contacté retiré de la liste',!str_contains($c->getResponse()->getContent(),'ct'.$c2->getId()));
echo "$ok ok / $ko ko\n";
