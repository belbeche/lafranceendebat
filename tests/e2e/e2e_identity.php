<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User, UserEvent, Inspiration};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k=new App\Kernel('test',true);$k->boot();
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$c=$k->getContainer();$em=$c->get('doctrine')->getManager();
$PW=bin2hex(random_bytes(8)).'Aa1!';
$mk=function($r,$mail=null) use($em){$u=new User();$u->setEmail($mail??'id'.uniqid().'@t.fr');$u->setPassword('x');$u->setFirstName('I');$u->setLastName('D');$u->setRoles($r);$em->persist($u);$em->flush();return $u;};
$pol=new App\Service\Security\DisposableEmailPolicy('/home/claude/app/config/disposable_domains.txt');
t('politique: bankwen.com suspect',$pol->isSuspicious('deboveh253@bankwen.com'));
t('politique: sous-domaine couvert',$pol->isSuspicious('x@a.mailinator.com'));
t('politique: gmail ok',!$pol->isSuspicious('walid@gmail.com'));
t('masque',App\Service\Security\DisposableEmailPolicy::mask('deboveh253@bankwen.com')==='d***@bankwen.com');
// notice publique
$b=new KernelBrowser($k);$b->request('GET','/notice-identite');t('notice 200',$b->getResponse()->getStatusCode()==200&&str_contains($b->getResponse()->getContent(),'identité réelle'));
// inscription avec e-mail jetable : refusee + journalisee
$ip='203.0.113.'.random_int(10,200);
$reg=function($mail) use($k,$ip,$PW){$b=new KernelBrowser($k,[]);$b->setServerParameter('REMOTE_ADDR',$ip);$cr=$b->request('GET','/inscription');$f=$cr->selectButton($cr->filter('form.reg-form button[type=submit]')->count()?$cr->filter('form.reg-form button[type=submit]')->text():'Créer mon compte');return $b;};
$b=new KernelBrowser($k);$b->setServerParameter('REMOTE_ADDR',$ip);$cr=$b->request('GET','/inscription');
$form=$cr->filter('form.reg-form')->form();
$vals=$form->getPhpValues();$name=array_key_first(array_filter($vals,'is_array'));
$data=$vals[$name];$data['email']='tentative'.uniqid().'@bankwen.com';$data['firstName']='Jean';$data['lastName']='Test';$data['plainPassword']=$PW;$data['ageOk']=1;$data['agreeCharter']=1;
$b->request('POST','/inscription',[$name=>$data]);
$st=$b->getResponse()->getStatusCode();
t("inscription jetable refusée ($st)",in_array($st,[422,200])&&str_contains($b->getResponse()->getContent(),'temporaire ou jetable'));
t('aucun compte créé',$em->getRepository(User::class)->count(['email'=>$data['email']])===0);
$n=(int)$em->createQuery("SELECT COUNT(e.id) FROM App\Entity\UserEvent e WHERE e.type='register_blocked' AND e.ip=:ip")->setParameter('ip',$ip)->getSingleScalarResult();
t("événement register_blocked ($n)",$n===1);
for($i=0;$i<2;$i++){$data['email']='x'.uniqid().'@bankwen.com';$b->request('POST','/inscription',[$name=>$data]);}
$b->request('GET','/inscription');
t('3e tentative: IP bloquée 429',$b->getResponse()->getStatusCode()==429);
t('IP signalée',(int)$em->createQuery("SELECT COUNT(e.id) FROM App\Entity\UserEvent e WHERE e.type='ip_flagged' AND e.ip=:ip")->setParameter('ip',$ip)->getSingleScalarResult()>=1);
// restriction => paiements bloqués
$u=$mk(['ROLE_USER','ROLE_ACTOR']);t('non restreint par défaut',!$u->isRestricted());
$u->restrict('test');t('restreint',$u->isRestricted());
$prov=$mk(['ROLE_USER','ROLE_ACTOR']);$prov->setStripeAccountId('acct_x');$prov->setStripeReady(true);
$tc=$c->has("test.service_container")?$c->get("test.service_container"):$c;$ms=$tc->get(App\Service\Payment\MarketplaceService::class);
t('vendeur sain peut vendre',$ms->canSell($prov)===true||(bool)$ms->canSell($prov));
$prov->restrict('x');t('vendeur restreint ne peut pas vendre',!$ms->canSell($prov));
$prov->liftRestriction();t('restriction levée',!$prov->isRestricted());
// don avec e-mail jetable
try{$tc->get(App\Service\DonationService::class)->start('Jean Dupont','1 rue de Paris 75001 Paris','a@bankwen.com',1000,'https://x/{TOKEN}','https://x');t('don jetable refusé',false);}catch(DomainException $e){t('don jetable refusé ('.mb_substr($e->getMessage(),0,30).')',true);}
// commande: acheteur restreint
$em->flush();
// commande
// connexion échouée x5 => suspect + journal
$vic=$mk(['ROLE_USER']);
$ipL='198.51.100.'.random_int(10,200);
for($i=0;$i<5;$i++){$bb=new KernelBrowser($k);$bb->setServerParameter('REMOTE_ADDR',$ipL);$cr=$bb->request('GET','/login');preg_match('/name="_csrf_token" value="([^"]+)"/',$bb->getResponse()->getContent(),$m);$bb->request('POST','/login',['_username'=>$vic->getEmail(),'_password'=>bin2hex(random_bytes(5)),'_csrf_token'=>$m[1]??'']);}
$q=fn($t)=> (int)$em->createQuery("SELECT COUNT(e.id) FROM App\Entity\UserEvent e WHERE e.type=:t AND e.ip=:ip")->setParameter('t',$t)->setParameter('ip',$ipL)->getSingleScalarResult();
t('5 échecs journalisés ('.$q('login_fail').')',$q('login_fail')>=5);
t('événement suspect créé',$q('login_suspect')===1);
t('avertissement au titulaire journalisé',$q('warning')===1);
// pages visitées liées à l'utilisateur
$cit=$mk(['ROLE_USER']);$bc=new KernelBrowser($k);$bc->setServerParameter('REMOTE_ADDR','192.0.2.44');$bc->setServerParameter('HTTP_CF_IPCOUNTRY','FR');$bc->loginUser($cit);
$bc->request('GET','/communaute');$bc->request('GET','/charte-de-paix');
$pg=$em->getRepository(UserEvent::class)->findBy(['user'=>$cit,'type'=>'page']);
t('pages journalisées par utilisateur ('.count($pg).')',count($pg)>=2);
t('IP et pays liés',$pg&&$pg[0]->getIp()==='192.0.2.44'&&$pg[0]->getCountry()==='FR');
// admin
$adm=$mk(['ROLE_ADMIN']);$ba=new KernelBrowser($k);$ba->loginUser($adm);
$ba->request('GET','/admin/securite');t('admin journal 200',$ba->getResponse()->getStatusCode()==200&&str_contains($ba->getResponse()->getContent(),'Journal de sécurité'));
$ba->request('GET','/admin/securite?ip=192.0.2');t('filtre IP',str_contains($ba->getResponse()->getContent(),'192.0.2.44'));
$ba->request('GET','/admin/securite/utilisateur/'.$cit->getId());t('journal utilisateur',$ba->getResponse()->getStatusCode()==200&&str_contains($ba->getResponse()->getContent(),'/communaute'));
$ba->request('GET','/admin/securite/export.csv');$csv=$ba->getInternalResponse()->getContent();t('export CSV',str_contains($csv,'192.0.2.44'));
$cr=$ba->request('GET','/admin/securite/utilisateur/'.$cit->getId());
$tok=$cr->filter('main input[name=_token]')->first()->attr('value');
$ba->request('POST','/admin/securite/utilisateur/'.$cit->getId().'/restreindre',['_token'=>$tok,'reason'=>'test']);
$em->clear();$cit=$em->find(User::class,$cit->getId());t('restriction manuelle (CSRF ok)',$cit->isRestricted());
$bm=new KernelBrowser($k);$bm->loginUser($mk(['ROLE_USER']));$bm->request('GET','/admin/securite');t('membre refusé',$bm->getResponse()->getStatusCode()==403);
// commande ligne de commande
$app=new Symfony\Bundle\FrameworkBundle\Console\Application($k);$app->setAutoExit(false);
$sus=$mk(['ROLE_USER'],'susp'.uniqid().'@mailinator.com');
$o=new Symfony\Component\Console\Output\BufferedOutput();$app->run(new Symfony\Component\Console\Input\ArrayInput(['command'=>'app:comptes:suspects','--appliquer'=>true]),$o);
$em->clear();$sus=$em->find(User::class,$sus->getId());t('commande: compte restreint et averti',$sus->isRestricted()&&$sus->getWarnedAt()!==null);
$rp=new ReflectionProperty(User::class,'warnedAt');$rp->setAccessible(true);$rp->setValue($sus,new DateTimeImmutable('-8 days'));$em->flush();
// 2e passage dans un processus séparé (comme le ferait la planification)
$tmp=sys_get_temp_dir().'/lfed_susp_'.uniqid().'.php';
file_put_contents($tmp,"<?php require '/home/claude/app/vendor/autoload.php';(new Symfony\\Component\\Dotenv\\Dotenv())->bootEnv('/home/claude/app/.env');\$k=new App\\Kernel('test',true);\$k->boot();\$a=new Symfony\\Bundle\\FrameworkBundle\\Console\\Application(\$k);\$a->setAutoExit(false);\$a->run(new Symfony\\Component\\Console\\Input\\ArrayInput(['command'=>'app:comptes:suspects','--appliquer'=>true]));");
shell_exec('APP_ENV=test php '.escapeshellarg($tmp).' 2>&1');@unlink($tmp);
$em->clear();$sus=$em->find(User::class,$sus->getId());t('commande: désactivé après le délai',!$sus->isActive());

// ---- inscription valide + piège à robots + confirmation e-mail + alertes admin
$tc=$c->has("test.service_container")?$c->get("test.service_container"):$c;
$ipR='203.0.113.'.random_int(201,250);
$br=new KernelBrowser($k);$br->setServerParameter('REMOTE_ADDR',$ipR);$cr=$br->request('GET','/inscription');
$vals=$cr->filter('form.reg-form')->form()->getPhpValues();$nm=array_key_first(array_filter($vals,'is_array'));$d=$vals[$nm];
$d['email']='ok'.uniqid().'@gmail.com';$d['firstName']='Marie';$d['lastName']='Durand';$d['plainPassword']=$PW;$d['ageOk']=1;$d['agreeCharter']=1;
$bot=$br->request('POST','/inscription',[$nm=>$d,'website'=>'http://spam.example']);
t('robot (champ caché) : rien créé',$em->getRepository(User::class)->count(['email'=>$d['email']])===0);
$br->request('POST','/inscription',[$nm=>$d]);
$new=$em->getRepository(User::class)->findOneBy(['email'=>$d['email']]);
t('inscription valide : compte créé',$new!==null);
t('inscription journalisée',$new&&$em->getRepository(UserEvent::class)->count(['user'=>$new,'type'=>'register'])===1);
if($new){
  $rp=new ReflectionProperty(User::class,'createdAt');$rp->setAccessible(true);$rp->setValue($new,new DateTimeImmutable('2026-11-10'));$em->flush();
  t('compte récent non confirmé : paiement bloqué',$new->needsEmailVerification()&&$new->isPaymentBlocked());
  $signer=$tc->get('uri_signer');
  $bad=new KernelBrowser($k);$bad->request('GET','/verifier-email/'.$new->getId().'?exp='.(time()+100).'&_hash=faux');
  $em->clear();$new=$em->find(User::class,$new->getId());t('lien falsifié refusé',!$new->isVerified());
  $url=$signer->sign('http://localhost/verifier-email/'.$new->getId().'?exp='.(time()+3600));
  $bv=new KernelBrowser($k);$bv->request('GET',preg_replace('#^http://localhost#','',$url));
  $em->clear();$new=$em->find(User::class,$new->getId());t('lien signé confirme l\'adresse',$new->isVerified()&&!$new->isPaymentBlocked());
  $old=$url;$exp=$signer->sign('http://localhost/verifier-email/'.$new->getId().'?exp='.(time()-10));
  $new->setIsVerified(false);$em->flush();$bv->request('GET',preg_replace('#^http://localhost#','',$exp));
  $em->clear();$new=$em->find(User::class,$new->getId());t('lien expiré refusé',!$new->isVerified());
}
// alertes administrateurs
$spy=new class extends App\Service\TransactionalMailer{public array $sent=[];public function __construct(){}public function adminAccountAlert(string $to,string $title,array $lines,?int $userId):void{$this->sent[]=[$to,$title];}};
$mkAdm=$mk(['ROLE_ADMIN']);
$al=new App\Service\Security\AdminAlerter($em,$spy,new Symfony\Component\Cache\Adapter\ArrayAdapter(),new Psr\Log\NullLogger());
$nAdm=(int)$em->createQuery("SELECT COUNT(u.id) FROM App\Entity\User u WHERE u.roles LIKE '%ROLE_ADMIN%' AND u.isActive=true")->getSingleScalarResult();
$ev=fn($ty,$ip='198.18.0.1')=>new UserEvent($ty,null,'x***@t.fr','d',null,$ip,'FR',null,'Chrome sur Windows');
$al->notify($ev('register'));t("inscription alerte tous les admins ($nAdm)",count($spy->sent)===$nAdm&&$nAdm>=1);
$spy->sent=[];foreach(['restricted','lifted','deactivated','warning','email_verified'] as $ty)$al->notify($ev($ty));
t('restriction/levée/désactivation/avertissement/confirmation alertées',count($spy->sent)===5*$nAdm);
$spy->sent=[];$al->notify($ev('page'));$al->notify($ev('login_ok'));$al->notify($ev('login_fail'));t('pages et connexions ordinaires non alertées',count($spy->sent)===0);
$al->notify($ev('register_blocked','198.18.0.9'));$al->notify($ev('register_blocked','198.18.0.9'));$al->notify($ev('register_blocked','198.18.0.9'));
t('rafale de robots : 1 alerte par heure et par IP',count($spy->sent)===$nAdm);
$al->notify($ev('register_blocked','198.18.0.10'));t('autre IP : nouvelle alerte',count($spy->sent)===2*$nAdm);
// lettre : e-mail jetable refusé
$bl=new KernelBrowser($k);$cr=$bl->request('GET','/lettre');$tok=$cr->filter('main input[name=_token]')->count()?$cr->filter('main input[name=_token]')->first()->attr('value'):'';
if($tok){$bl->request('POST','/lettre/inscription',['_token'=>$tok,'email'=>'a'.uniqid().'@yopmail.com','consent'=>1]);$bl->followRedirect();t('lettre : e-mail jetable refusé',str_contains($bl->getResponse()->getContent(),'jetable'));}
// contributeurs
$i=new Inspiration('Personne Test','sante','Résumé suffisamment long pour la fiche de test, avec une description complète et sourcée.','https://example.org/s');$i->publish();$i->setAdvances("Première avancée\nSeconde avancée");$em->persist($i);$em->flush();
$bp=new KernelBrowser($k);$bp->request('GET','/ils-ont-contribue/'.$i->getId());$h=$bp->getResponse()->getContent();
t('fiche personne 200 + avancées',$bp->getResponse()->getStatusCode()==200&&str_contains($h,'Première avancée')&&str_contains($h,'Seconde avancée'));
$bp->request('GET','/ils-ont-contribue');t('liste: lien vers la fiche',str_contains($bp->getResponse()->getContent(),'/ils-ont-contribue/'.$i->getId()));
$p=new Inspiration('Brouillon','autre','Résumé suffisamment long pour la fiche de test, avec une description complète et sourcée.','https://example.org/s');$em->persist($p);$em->flush();
$bp->request('GET','/ils-ont-contribue/'.$p->getId());t('fiche non publiée: 404',$bp->getResponse()->getStatusCode()==404);
$ba->request('GET','/admin/rubriques/contributeurs/'.$i->getId().'/avancees');t('admin avancées 200',$ba->getResponse()->getStatusCode()==200);
echo "\n$ok OK / $ko KO\n";
