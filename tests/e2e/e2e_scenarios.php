<?php
require '/home/claude/app/vendor/autoload.php';
$_ENV['PUBLISHER_EMAIL']=$_SERVER['PUBLISHER_EMAIL']='admin@lfed.test'; putenv('MAIL_FROM=noreply@lfed.test'); $_ENV['MAIL_FROM']=$_SERVER['MAIL_FROM']='noreply@lfed.test';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
$_ENV['MAIL_FROM']=$_SERVER['MAIL_FROM']='noreply@lfed.test'; putenv('MAIL_FROM=noreply@lfed.test');
use App\Entity\{User,RoleRequest,Proposition,ServiceOffer,ServiceOrder};
function client($email=null){ $k=new App\Kernel('test',true); $k->boot(); $c=$k->getContainer()->get('test.client'); $c->disableReboot(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang","fr"));
  if($email){ $u=$k->getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email'=>$email]); $c->loginUser($u);} return $c; }
function em($c){ return $c->getContainer()->get('doctrine')->getManager(); }
function mails($c){ $r=[]; foreach($c->getContainer()->get('mailer.message_logger_listener')->getEvents()->getMessages() as $m){$r[]=$m->getSubject().' -> '.implode(',',array_map(fn($a)=>$a->getAddress(),$m->getTo()));} return $r; }
$ok=0;$ko=0; function t($n,$c){global $ok,$ko; if($c){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$c=client(); $em=em($c);
foreach(['DELETE FROM App\Entity\OrderMessage','DELETE FROM App\Entity\ServiceOrder','DELETE FROM App\Entity\ServiceOffer','DELETE FROM App\Entity\RoleRequest','DELETE FROM App\Entity\Proposition'] as $q){ try{$em->createQuery($q)->execute();}catch(\Throwable $e){} }
foreach($em->getRepository(User::class)->findBy([]) as $u){ if(str_starts_with($u->getEmail(),'sc_')) $em->remove($u);} $em->flush();
function mk($em,$m,$roles=[]){ $u=(new User())->setEmail($m)->setFirstName('Sc')->setLastName(ucfirst(substr($m,3,4)))->setPassword('x')->setRoles($roles)->setIsActive(true); $u->setCreatedAt(new DateTimeImmutable()); $em->persist($u); return $u; }
mk($em,'sc_citoyen@x.fr'); mk($em,'sc_admin@x.fr',['ROLE_ADMIN']); $prov=mk($em,'sc_acteur@x.fr',['ROLE_ACTOR']); $em->flush();

echo "--- S1 Visiteur anonyme\n";
$c=client(); foreach(['/','/marche-a-suivre','/contacts-utiles','/plan-marshall-citoyen','/communaute','/debats','/propositions','/scrutins','/services','/acteurs-publics','/charte-de-paix','/contact'] as $p){ $c->request('GET',$p); t("anonyme voit $p",$c->getResponse()->getStatusCode()===200); }
foreach(['/mon-espace','/propositions/nouvelle','/candidater','/services/mes-commandes','/espace/elu'] as $p){ $c->request('GET',$p); t("anonyme redirige vers connexion: $p",$c->getResponse()->isRedirect()); }
$c->request('GET','/admin'); t('anonyme interdit admin',in_array($c->getResponse()->getStatusCode(),[302,401,403]));

echo "--- S2 Citoyen: proposer une idee\n";
$c=client('sc_citoyen@x.fr'); $c->request('GET','/mon-espace'); t('citoyen voit Mon espace',$c->getResponse()->getStatusCode()===200);
$c->request('GET','/espace-acteur'); t('citoyen sans droit acteur redirige',$c->getResponse()->isRedirect());
$c->request('GET','/admin/services'); t('citoyen interdit admin (code '.$c->getResponse()->getStatusCode().')',in_array($c->getResponse()->getStatusCode(),[302,403,404]));
$cr=$c->request('GET','/propositions/nouvelle'); t('formulaire proposition',$c->getResponse()->getStatusCode()===200);
$f=$cr->filter('form')->reduce(fn($n)=>$n->filter('textarea')->count()>0)->first()->form();
foreach($f->all() as $k=>$fld){ $cls=get_class($fld); if(str_contains($cls,'ChoiceFormField')){ if($fld->getType()==='checkbox'){ $fld->tick(); } elseif($fld->getType()==='radio'){ $o=$fld->availableOptionValues(); $fld->select($o[1]??$o[0]); } elseif($fld->getType()==='select'){ $o=$fld->availableOptionValues(); $fld->select($o[1]??$o[0]); } } elseif(!str_contains($k,'_token') && !str_contains($k,'petitionGoal') && !str_contains($cls,'FileFormField')){ if(trim((string)$fld->getValue())===''){ $fld->setValue(str_contains($k,'sourceUrl')?'https://www.service-public.fr/x':'Une donnée ne doit être demandée qu\'une seule fois, avec un coût estimé et un responsable. Texte suffisamment long pour la validation.'); } } }
$c->submit($f); if(getenv('DBG')){ echo $c->getResponse()->getStatusCode(),' ',implode(' / ',$c->getCrawler()->filter('.invalid-feedback,.alert,.form-error-message')->each(fn($n)=>trim($n->text()))),"\n"; } $n=em($c)->getRepository(Proposition::class)->count([]);
t('proposition enregistree en attente',$n===1 && em($c)->getRepository(Proposition::class)->findOneBy([])->isPending());
$m=mails($c); t('mail admin + mail auteur pour la proposition',count($m)>=2 && count(array_filter($m,fn($x)=>str_contains($x,'admin@lfed.test')))>=1 && count(array_filter($m,fn($x)=>str_contains($x,'sc_citoyen@x.fr')))>=1); echo '  ',implode(' | ',$m),"\n";

echo "--- S3 Candidature a un role (elu)\n";
$long=str_repeat('Je souhaite repondre aux propositions des citoyens de ma commune avec transparence. ',2);
$c=client('sc_citoyen@x.fr'); $cr=$c->request('GET','/candidater?role=ROLE_ELU'); t('formulaire candidature (compte neuf, sans limite)',$c->getResponse()->getStatusCode()===200 && !str_contains($c->getResponse()->getContent(),'au moins'));
$f=$cr->selectButton('Envoyer ma candidature')->form(['role'=>'ROLE_ELU','motivation'=>$long,'organisation'=>'Mairie Test','proof_url'=>'https://www.mairie-test.fr/conseil','phone'=>'0612345678']); $c->submit($f);
t('candidature creee',em($c)->getRepository(RoleRequest::class)->count([])===1);
$m=mails($c); t('mail admin + accuse reception candidat',count($m)>=2 && count(array_filter($m,fn($x)=>str_contains($x,'admin@lfed.test')))>=1 && count(array_filter($m,fn($x)=>str_contains($x,'sc_citoyen@x.fr')))>=1); echo '  ',implode(' | ',$m),"\n";
$rid=em($c)->getRepository(RoleRequest::class)->findOneBy([])->getId();
$a=client('sc_admin@x.fr'); $cr=$a->request('GET','/admin/candidatures-droits'); t('admin voit les candidatures',$a->getResponse()->getStatusCode()===200);
$tok=$cr->filter('main input[name=_token]')->first()->attr('value');
$a->request('POST',"/admin/candidatures-droits/$rid/approve",['_token'=>$tok]); $m=mails($a);
$u=em($a)->getRepository(User::class)->findOneBy(['email'=>'sc_citoyen@x.fr']); em($a)->refresh($u);
t('admin approuve: role ROLE_ELU accorde',in_array('ROLE_ELU',$u->getRoles(),true)); t('mail de decision au candidat',count(array_filter($m,fn($x)=>str_contains($x,'sc_citoyen@x.fr')))>=1); echo '  ',implode(' | ',$m),"\n";
$c=client('sc_citoyen@x.fr'); $c->request('GET','/espace/elu'); t('elu entre dans son espace',$c->getResponse()->getStatusCode()===200);
$c->request('GET','/espace/association'); t('elu pas dans espace association',$c->getResponse()->isRedirect());

echo "--- S4 Prestataire et client: commande\n";
$em=em($c); $prov=$em->getRepository(User::class)->findOneBy(['email'=>'sc_acteur@x.fr']); $buy=$em->getRepository(User::class)->findOneBy(['email'=>'sc_citoyen@x.fr']);
$o=(new ServiceOffer($prov))->setTitle('Médiation de quartier')->setDescription(str_repeat('Séance de médiation. ',6))->setCategory('mediation')->setPriceCents(10000)->setStatus('Publiée'); $em->persist($o);
$ord=new ServiceOrder($o,$buy,10000,500); $ord->setStripeSessionId('cs_test_sc'); $em->persist($ord); $em->flush();
$k=$c->getKernel(); $svc=$c->getContainer()->get(App\Service\Payment\MarketplaceService::class);
$mailer=null; $sent=[]; 
$svc->markPaid('cs_test_sc','pi_sc',10000); $em->refresh($ord); t('paiement confirme: commande payee',$ord->getStatus()===ServiceOrder::PAID);
$m=mails($c); echo '  ',implode(' | ',$m),"\n";
t('mails commande payee: prestataire, client, admin',count(array_filter($m,fn($x)=>str_contains($x,'sc_acteur@x.fr')))>=1 && count(array_filter($m,fn($x)=>str_contains($x,'sc_citoyen@x.fr')))>=1 && count(array_filter($m,fn($x)=>str_contains($x,'admin@lfed.test')))>=1);
$ct=client('sc_citoyen@x.fr'); $cr=$ct->request('GET','/services/commande/'.$ord->getId().'/messages'); t('client ouvre la messagerie',$ct->getResponse()->getStatusCode()===200);
$tk=$cr->filter('main input[name=_token]')->first()->attr('value'); $ct->request('POST','/services/commande/'.$ord->getId().'/messages',['_token'=>$tk,'body'=>'Bonjour, quand pouvons-nous commencer ?']); $m=mails($ct);
t('message envoye + mail au prestataire',count(array_filter($m,fn($x)=>str_contains($x,'sc_acteur@x.fr')))>=1); echo '  ',implode(' | ',$m),"\n";
$cr=$ct->request('GET','/services/commande/'.$ord->getId().'/messages'); $tk=$cr->filter('main input[name=_token]')->first()->attr('value'); $ct->request('POST','/services/commande/'.$ord->getId().'/messages',['_token'=>$tk,'body'=>'Appelez-moi au 06 12 34 56 78']);
t('coordonnees refusees dans la messagerie',em($ct)->getRepository(App\Entity\OrderMessage::class)->count([])===1);
$cp=client('sc_acteur@x.fr'); $cp->request('GET','/espace-acteur'); t('prestataire voit son espace',$cp->getResponse()->getStatusCode()===200);
$cp->request('GET','/services/commande/'.$ord->getId().'/messages'); t('prestataire voit la messagerie',$cp->getResponse()->getStatusCode()===200);
$cx=client('sc_admin@x.fr'); $cx->request('GET','/admin/services'); t('admin voit services et commandes',$cx->getResponse()->getStatusCode()===200);

echo "--- S5b Revendications et lettres par langue\n";
$em=em(client()); foreach($em->getRepository(App\Entity\Revendication::class)->findBy(['slug'=>['sc-test-fr','sc-test-en']]) as $x){$em->remove($x);} $em->flush();
foreach(['fr'=>'Revendication test en francais','en'=>'Test claim in English'] as $lc=>$ti){ $r=new App\Entity\Revendication(); $r->setTitle($ti)->setSlug('sc-test-'.$lc)->setSummary('Resume de test.')->setGroupLabel(App\Entity\Revendication::GROUPS[0])->setTheme(App\Entity\Proposition::THEMES[0])->setRaisedAt(new DateTimeImmutable())->setUpdatedAt(new DateTimeImmutable())->setLocale($lc); $r->setIsPublished(true); try{$em->persist($r);$em->flush();}catch(\Throwable $e){echo '  (rev: '.substr($e->getMessage(),0,120).")\n";$em=em(client());} }
foreach(['fr','en'] as $lc){ $c=client(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang",$lc)); $c->request('GET','/revendications'); $h=$c->getResponse()->getContent(); $want=$lc==='fr'?'Revendication test en francais':'Test claim in English'; $not=$lc==='fr'?'Test claim in English':'Revendication test en francais'; t("revendications $lc: toutes listees",str_contains($h,$want)&&str_contains($h,$not)); }
$c=client(); $c->request('GET','/revendications?langue=toutes'); t('revendications: liste toutes langues (200)',$c->getResponse()->getStatusCode()==200);
$em=em(client()); $repo=$em->getRepository(App\Entity\Lettre::class);
$l=new App\Entity\Lettre(); $l->setSubject('x')->setBody('y')->setLocale('en'); t('lettre: langue en conservee',$l->getLocale()==='en'); $l->setLocale('zz'); t('lettre: langue inconnue = toutes',$l->getLocale()===null);
$a1=new App\Entity\ActorContact('sc_en@mairie-test.fr','Mairie EN','https://x.fr/c'); $a1->setLocale('en'); $a2=new App\Entity\ActorContact('sc_fr@mairie-test.fr','Mairie FR','https://x.fr/c'); $a2->setLocale('fr');
foreach($em->getRepository(App\Entity\ActorContact::class)->findBy([]) as $z){$em->remove($z);} $em->flush(); $a1->giveConsent(); $a2->giveConsent(); $em->persist($a1);$em->persist($a2);$em->flush();
$ar=$em->getRepository(App\Entity\ActorContact::class); t('acteurs: filtre langue en = 1',$ar->countEligible(false,'en')===1); t('acteurs: filtre langue fr = 1',$ar->countEligible(false,'fr')===1); t('acteurs: sans filtre = 2',$ar->countEligible(false,null)===2); t('acteurs: langue absente = 0',$ar->countEligible(false,'de')===0);
echo "--- S5 Langues\n";
foreach(['en','es','de','ar','tr'] as $l){ $c=client(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang",$l)); $c->request('GET','/'); $h=$c->getResponse()->getContent(); t("accueil $l sans cle brute",$c->getResponse()->getStatusCode()===200 && !preg_match('/\b(guide|eco|home\.how|home\.live|door)\.[a-z0-9_.]+/',strip_tags($h))); }
echo "\n$ok ok / $ko ko\n";
