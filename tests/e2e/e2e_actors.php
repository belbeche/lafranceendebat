<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
$_SERVER['MAIL_FROM']=$_ENV['MAIL_FROM']='lettre@lafranceendebat.fr'; putenv('MAIL_FROM=lettre@lafranceendebat.fr');
use App\Entity\{User,ActorContact,Lettre,NewsletterSubscriber};
function client($email=null){ $k=new App\Kernel('test',true); $k->boot(); $c=$k->getContainer()->get('test.client'); $c->disableReboot(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang","fr"));
  if($email){ $u=$k->getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email'=>$email]); $c->loginUser($u);} return $c; }
function em($c){ return $c->getContainer()->get('doctrine')->getManager(); }
$ok=0;$ko=0; function t($n,$c){global $ok,$ko; if($c){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$H=['HTTP_ORIGIN'=>'http://localhost','HTTP_REFERER'=>'http://localhost/'];
$c=client(); $em=em($c);
$em->createQuery('DELETE FROM App\Entity\ActorContact')->execute(); $em->createQuery('DELETE FROM App\Entity\Lettre')->execute();
$em->createQuery("DELETE FROM App\Entity\NewsletterSubscriber s WHERE s.email LIKE 'zz_%'")->execute();
foreach($em->getRepository(User::class)->findBy([]) as $u){ if(str_starts_with($u->getEmail(),'a_')) $em->remove($u);} $em->flush();
foreach(['a_adm@x.fr'=>['ROLE_ADMIN'],'a_usr@x.fr'=>[]] as $m=>$r){ $u=(new User())->setEmail($m)->setFirstName('A')->setLastName('B')->setPassword('x')->setRoles($r)->setIsActive(true); $u->setCreatedAt(new DateTimeImmutable('-30 days')); $em->persist($u);} 
$sub=new NewsletterSubscriber(); $sub->setEmail('zz_desinscrit@mairie-test.fr'); $sub->setUnsubscribedAt(new DateTimeImmutable()); $em->persist($sub); $em->flush();
$csv="email;organisation;fonction;categorie;source_url\nmairie@ville-a.fr;Mairie de A;Secrétariat;commune;https://www.ville-a.fr/contact\nmairie@ville-a.fr;Mairie de A;Doublon;commune;https://www.ville-a.fr/contact\njean.dupont@gmail.com;Mairie B;Maire;commune;https://www.ville-b.fr\nbad-email;Mairie C;x;commune;https://www.ville-c.fr\ncontact@ville-d.fr;Mairie D;x;commune;\nzz_desinscrit@mairie-test.fr;Mairie E;x;commune;https://www.ville-e.fr/c\ndepute@assemblee-nationale.fr;Député X;Député;parlementaire;https://www2.assemblee-nationale.fr/deputes\n";
$f=sys_get_temp_dir().'/actors.csv'; file_put_contents($f,$csv);
$c=client('a_usr@x.fr'); $c->request('GET','/admin/acteurs-publics'); t('membre 403',$c->getResponse()->getStatusCode()===403);
$c=client('a_adm@x.fr'); $cr=$c->request('GET','/admin/acteurs-publics'); t('admin 200',$c->getResponse()->getStatusCode()===200);
$tok=$cr->filter('main input[name=_token]')->first()->attr('value');
$c->request('POST','/admin/acteurs-publics/importer',['_token'=>$tok],['csv'=>new Symfony\Component\HttpFoundation\File\UploadedFile($f,'actors.csv','text/csv',null,true)],$H); t('sans attestation: rien importe', em($c)->getRepository(ActorContact::class)->count([])===0);
$c->request('POST','/admin/acteurs-publics/importer',['_token'=>$tok,'attest'=>1],['csv'=>new Symfony\Component\HttpFoundation\File\UploadedFile($f,'actors.csv','text/csv',null,true)],$H);
$n=em($c)->getRepository(ActorContact::class)->count([]); t('2 contacts valides importes', $n===2);
$body=$c->getResponse()->getContent(); t('rapport: gmail, invalide, source, suppression refuses', str_contains($body,'grand public') && str_contains($body,'Adresse invalide') && str_contains($body,'source_url') && str_contains($body,'exclue'));
$c->request('POST','/admin/acteurs-publics/importer',['_token'=>$tok,'attest'=>1],['csv'=>new Symfony\Component\HttpFoundation\File\UploadedFile($f,'actors.csv','text/csv',null,true)],$H); t('reimport idempotent', em($c)->getRepository(ActorContact::class)->count([])===2);
// lettre acteurs
$em=em($c); $l=(new Lettre())->setSubject('Rejoignez le dialogue citoyen')->setBody(str_repeat('Bonjour, nous proposons un espace de dialogue pacifié. ',3))->setAudience('invitation'); $em->persist($l); $em->flush(); $lid=$l->getId();
$cr=$c->request('GET',"/admin/lettres/$lid"); t('show invitation + 2 destinataires', str_contains($c->getResponse()->getContent(),'invitation unique') && str_contains($c->getResponse()->getContent(),'à 2 acteur'));
$tk=$cr->filter('form[action$="/envoyer"] input[name=_token]')->first()->attr('value');
$c->request('POST',"/admin/lettres/$lid/envoyer",['_token'=>$tk,'confirm_count'=>'9'],[],$H); $em->clear(); t('mauvais nombre: pas de mise en file', $em->find(Lettre::class,$lid)->getStatus()==='brouillon');
$c->request('POST',"/admin/lettres/$lid/envoyer",['_token'=>$tk,'confirm_count'=>'2'],[],$H); $em->clear(); $st=$em->find(Lettre::class,$lid)->getStatus(); t('mise en file',$st==='en file');
// envoi via commande
$k=new App\Kernel('test',true); $k->boot(); $app=new Symfony\Bundle\FrameworkBundle\Console\Application($k); $app->setAutoExit(false);
$out=new Symfony\Component\Console\Output\BufferedOutput(); $code=$app->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'app:lettre:envoyer','--delay-ms'=>'0']),$out);
$em=$k->getContainer()->get('doctrine')->getManager(); $em->clear(); $l=$em->find(Lettre::class,$lid); $acts=$em->getRepository(ActorContact::class)->findAll();
t('commande ok', $code===0); 
$code=$app->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'app:lettre:envoyer','--delay-ms'=>'0']),$out); $em->clear(); $l=$em->find(Lettre::class,$lid);
t('lettre envoyee, 2 envois 0 echec', $l->getStatus()==='envoyee' && $l->getSentCount()===2 && $l->getFailedCount()===0);
$acts=$em->getRepository(ActorContact::class)->findAll(); t('contacts marques contactes', count(array_filter($acts,fn($a)=>$a->getLastContactedAt()&&$a->getContactCount()===1))===2);
t('invitation unique: plus personne a inviter', $em->getRepository(ActorContact::class)->countEligible(true)===0);
t('sans consentement: aucun message suivant', $em->getRepository(ActorContact::class)->countEligible(false)===0);
t('invites enregistres', count(array_filter($acts,fn($a)=>$a->getInvitedAt()))===2);
// consentement
$a0=$acts[0]; $a1=$acts[1]; $T=new App\Service\Lettre\LettreTokens($k->getContainer()->getParameter('kernel.secret'));
$p=client(); $p->request('GET','/acteurs/accepter/'.$a0->getId().'/bad'); t('consentement: lien invalide 400',$p->getResponse()->getStatusCode()===400);
$ct=$T->actorConsent($a0->getId(),$a0->getEmail()); $p->request('GET','/acteurs/accepter/'.$a0->getId().'/'.$ct); t('consentement: page 200 (GET ne consent pas)',$p->getResponse()->getStatusCode()===200); $e=em($p); $e->clear(); t('GET seul: pas de consentement',$e->find(ActorContact::class,$a0->getId())->getConsentAt()===null);
$p->request('POST','/acteurs/accepter/'.$a0->getId().'/'.$ct); $e=em($p); $e->clear(); t('POST: consentement enregistre',$e->find(ActorContact::class,$a0->getId())->hasConsent());
$em->clear(); $c2=$em->find(ActorContact::class,$a0->getId()); $c2->setStatus('actif');
$q=$em->createQuery('UPDATE App\Entity\ActorContact a SET a.lastContactedAt = :d'); $q->setParameter('d',new DateTimeImmutable('-10 days'))->execute();
t('consentant joignable apres 7 jours, pas l autre', $em->getRepository(ActorContact::class)->countEligible(false)===1);
$l2=(new Lettre())->setSubject('Nouveauté de la plateforme')->setBody(str_repeat('Bonjour, voici les nouveautés. ',3))->setAudience('acteurs'); $em->persist($l2); $em->flush(); $l2->setStatus('en file')->setTargetCount(1); $em->flush(); $l2id=$l2->getId();
$app->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'app:lettre:envoyer','--delay-ms'=>'0']),$out); $app->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'app:lettre:envoyer','--delay-ms'=>'0']),$out); $em->clear(); $l2=$em->find(Lettre::class,$l2id);
t('2e lettre: envoyee au seul consentant', $l2->getSentCount()===1 && $l2->getStatus()==='envoyee');
// desinscription
$a=$acts[1]; $aid=$a->getId(); $tok=(new App\Service\Lettre\LettreTokens($k->getContainer()->getParameter('kernel.secret')))->actorUnsubscribe($aid,$a->getEmail());
$p=client(); $p->request('GET',"/acteurs/desinscription/$aid/bad"); t('lien invalide 400',$p->getResponse()->getStatusCode()===400);
$p->request('GET',"/acteurs/desinscription/$aid/$tok"); t('page de confirmation 200',$p->getResponse()->getStatusCode()===200);
$p->request('POST',"/acteurs/desinscription/$aid/$tok"); $e=em($p); $e->clear(); t('desinscrit definitivement', $e->find(ActorContact::class,$aid)->getStatus()==='desinscrit');
// exclusion admin + test d'envoi
$cr=$c->request('GET','/admin/acteurs-publics'); t('liste affiche contacts', str_contains($c->getResponse()->getContent(),'Mairie de A'));
$cr=$c->request('GET',"/admin/lettres/$lid"); echo "OK  fin\n";
echo "$ok ok / $ko ko\n";
