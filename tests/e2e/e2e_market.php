<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,ServiceOffer,ServiceOrder};
use App\Service\Payment\Testing\FakeGateway;
function client($email=null){ $k=new App\Kernel('test',true); $k->boot(); $c=$k->getContainer()->get('test.client'); $c->disableReboot(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang","fr"));
  if($email){ $u=$k->getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email'=>$email]); $c->loginUser($u);} return $c; }
function em($c){ return $c->getContainer()->get('doctrine')->getManager(); }
$ok=0;$ko=0; function t($n,$c){global $ok,$ko; if($c){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$H=['HTTP_ORIGIN'=>'http://localhost','HTTP_REFERER'=>'http://localhost/'];
$c=client(); $em=em($c);
$em->createQuery('DELETE FROM App\Entity\OrderMessage')->execute(); $em->createQuery('DELETE FROM App\Entity\ServiceOrder')->execute(); $em->createQuery('DELETE FROM App\Entity\ServiceOffer')->execute();
foreach($em->getRepository(User::class)->findBy([]) as $u){ if(str_starts_with($u->getEmail(),'m_')) $em->remove($u);} $em->flush();
function mk($em,$m,$roles=[],$ready=false){ $u=(new User())->setEmail($m)->setFirstName('Aa')->setLastName('Bb')->setPassword('x')->setRoles($roles)->setIsActive(true); $u->setCreatedAt(new DateTimeImmutable('-30 days')); if($ready){$u->setStripeAccountId('acct_1');$u->setStripeReady(true);} $em->persist($u); }
mk($em,'m_prov@x.fr',['ROLE_ACTOR'],true); mk($em,'m_noready@x.fr',['ROLE_ACTOR']); mk($em,'m_buy@x.fr'); mk($em,'m_other@x.fr'); mk($em,'m_adm@x.fr',['ROLE_ADMIN']); $em->flush();
$prov=$em->getRepository(User::class)->findOneBy(['email'=>'m_prov@x.fr']);
$o=(new ServiceOffer($prov))->setTitle('Médiation de quartier')->setDescription(str_repeat('Séance de médiation. ',6))->setCategory('mediation')->setPriceCents(10001)->setStatus('Publiée'); $em->persist($o);
$o2=(new ServiceOffer($prov))->setTitle('Brouillon en attente')->setDescription('x')->setCategory('etude')->setPriceCents(1000); $em->persist($o2); $em->flush(); $oid=$o->getId(); $o2id=$o2->getId();
// commission
$svc=$c->getContainer()->get(App\Service\Payment\MarketplaceService::class);
t('commission 5% de 100,01 = 500 (arrondi)', $svc->breakdown(10001)==['amount'=>10001,'commission'=>500,'net'=>9501]);
t('somme commission+net = montant', ($b=$svc->breakdown(3333))['commission']+$b['net']===3333);
$c=client(); $c->request('GET','/services'); t('liste publique 200 + offre', $c->getResponse()->getStatusCode()===200 && str_contains($c->getResponse()->getContent(),'Médiation de quartier') && !str_contains($c->getResponse()->getContent(),'Brouillon'));
$c->request('GET',"/services/$oid"); t('fiche affiche repartition', str_contains($c->getResponse()->getContent(),'500,00') || str_contains($c->getResponse()->getContent(),'5,00'));
$c->request('GET',"/services/$o2id"); t('offre non publiee 404', $c->getResponse()->getStatusCode()===404);
$c->request('POST',"/services/$oid/commander",['_token'=>'x'],[],$H); t('commander anonyme -> login', $c->getResponse()->isRedirect() && str_contains($c->getResponse()->headers->get('Location'),'login'));
$c->request('GET','/espace-acteur'); t('espace acteur anonyme -> login', $c->getResponse()->isRedirect());
$c=client('m_buy@x.fr'); $c->request('GET','/espace-acteur'); t('simple membre redirige vers candidature', $c->getResponse()->isRedirect() && str_contains($c->getResponse()->headers->get('Location'),'candidater'));
// commande
FakeGateway::$calls=[];
$cr=$c->request('GET',"/services/$oid"); $tok=$cr->filter('main input[name=_token]')->first()->attr('value');
$c->request('POST',"/services/$oid/commander",['_token'=>'bad','cgv'=>1],[],$H); t('csrf invalide: pas de commande', em($c)->getRepository(ServiceOrder::class)->count([])===0);
$c->request('POST',"/services/$oid/commander",['_token'=>$tok],[],$H); t('sans cgv: pas de commande', em($c)->getRepository(ServiceOrder::class)->count([])===0);
$c->request('POST',"/services/$oid/commander",['_token'=>$tok,'cgv'=>1,'no_political'=>1,'note'=>'Bonjour'],[],$H);
$ord=em($c)->getRepository(ServiceOrder::class)->findOneBy([]);
t('redirige vers checkout stripe', $c->getResponse()->isRedirect() && str_contains($c->getResponse()->headers->get('Location'),'checkout.stripe.test'));
t('commande creee attente_paiement, montants figes', $ord && $ord->getStatus()==='attente_paiement' && $ord->getAmountCents()===10001 && $ord->getCommissionCents()===500 && $ord->getNetCents()===9501);
t('montant envoye a Stripe = prix', in_array('checkout:'.$ord->getId().':10001',FakeGateway::$calls));
$oidd=$ord->getId();
// buyer == provider
$cp=client('m_prov@x.fr'); $cr=$cp->request('GET',"/services/$oid"); $tp=$cr->filter('main input[name=_token]')->first()->attr('value');
$cp->request('POST',"/services/$oid/commander",['_token'=>$tp,'cgv'=>1,'no_political'=>1],[],$H); t('auto-commande refusee', em($cp)->getRepository(ServiceOrder::class)->count([])===1);
// webhook
$sess='cs_fake_'.$oidd; $evt=json_encode(['type'=>'checkout.session.completed','data'=>['object'=>['id'=>$sess,'payment_status'=>'paid','payment_intent'=>'pi_1','amount_total'=>10001]]]);
function sig($p,$s='whsec_test_secret',$t=null){ $t=$t??time(); return "t=$t,v1=".hash_hmac('sha256',"$t.$p",$s); }
$w=client(); $w->request('POST','/webhook/stripe',[],[],[],$evt); t('webhook sans signature 403',$w->getResponse()->getStatusCode()===403);
$w->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>sig($evt,'whsec_wrong')],$evt); t('webhook mauvaise signature 403',$w->getResponse()->getStatusCode()===403);
$w->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>sig($evt,'whsec_test_secret',time()-4000)],$evt); t('webhook signature perimee 403',$w->getResponse()->getStatusCode()===403);
$bad=json_encode(['type'=>'checkout.session.completed','data'=>['object'=>['id'=>$sess,'payment_status'=>'paid','payment_intent'=>'pi_1','amount_total'=>1]]]);
$w->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>sig($bad)],$bad); $em=em($w); $em->clear(); t('montant incoherent: pas valide', $em->find(ServiceOrder::class,$oidd)->getStatus()==='attente_paiement');
$w->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>sig($evt)],$evt); t('webhook valide 200',$w->getResponse()->getStatusCode()===200); $em->clear(); t('commande payee', $em->find(ServiceOrder::class,$oidd)->getStatus()==='payee');
$w->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>sig($evt)],$evt); $em->clear(); t('rejeu idempotent (toujours payee)', $em->find(ServiceOrder::class,$oidd)->getStatus()==='payee');
// confirm
$co=client('m_other@x.fr'); $co->request('POST',"/services/commande/$oidd/confirmer",['_token'=>'x'],[],$H); t('tiers ne peut pas confirmer 403',$co->getResponse()->getStatusCode()===403);
$cp=client('m_prov@x.fr'); $cp->request('POST',"/services/commande/$oidd/confirmer",['_token'=>'x'],[],$H); t('prestataire ne peut pas se payer lui-meme 403',$cp->getResponse()->getStatusCode()===403);
FakeGateway::$calls=[]; $cb=client('m_buy@x.fr'); $cr=$cb->request('GET','/services/mes-commandes'); t('mes commandes 200 + bouton', $cb->getResponse()->getStatusCode()===200 && str_contains($cb->getResponse()->getContent(),'La prestation a été réalisée'));
$tk=$cr->filter('main input[name=_token]')->first()->attr('value');
FakeGateway::$failTransfer=true; $cb->request('POST',"/services/commande/$oidd/confirmer",['_token'=>$tk],[],$H); $em=em($cb); $em->clear(); t('echec transfert: commande reste payee (rien perdu)', $em->find(ServiceOrder::class,$oidd)->getStatus()==='payee');
FakeGateway::$failTransfer=false; $cb->request('POST',"/services/commande/$oidd/confirmer",['_token'=>$tk],[],$H); $em->clear(); $x=$em->find(ServiceOrder::class,$oidd);
t('confirmation: terminee + transfert net 95,01', $x->getStatus()==='terminee' && in_array("transfer:$oidd:9501:acct_1",FakeGateway::$calls) && $x->getStripeTransferId());
FakeGateway::$calls=[]; $cb->request('POST',"/services/commande/$oidd/confirmer",['_token'=>$tk],[],$H); t('double confirmation: aucun 2e transfert', count(array_filter(FakeGateway::$calls,fn($s)=>str_starts_with($s,'transfer')))===0);
$cp->request('POST',"/services/commande/$oidd/rembourser",['_token'=>'x'],[],$H);
// remboursement
$cb=client('m_buy@x.fr'); $cr=$cb->request('GET',"/services/$oid"); $tk2=$cr->filter('main input[name=_token]')->first()->attr('value');
$cb->request('POST',"/services/$oid/commander",['_token'=>$tk2,'cgv'=>1,'no_political'=>1],[],$H); $o2x=em($cb)->getRepository(ServiceOrder::class)->findOneBy(['status'=>'attente_paiement']); $id2=$o2x->getId();
$e2=json_encode(['type'=>'checkout.session.completed','data'=>['object'=>['id'=>'cs_fake_'.$id2,'payment_status'=>'paid','payment_intent'=>'pi_2','amount_total'=>10001]]]); $w=client(); $w->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>sig($e2)],$e2);
$co=client('m_other@x.fr'); $co->request('POST',"/services/commande/$id2/rembourser",['_token'=>'x'],[],$H); t('tiers ne peut pas rembourser 403',$co->getResponse()->getStatusCode()===403);
$cp=client('m_prov@x.fr'); $cr=$cp->request('GET','/espace-acteur'); t('espace acteur prestataire 200',$cp->getResponse()->getStatusCode()===200);
$tkr=$cr->filter('form[action$="rembourser"] input[name=_token]')->first()->attr('value'); FakeGateway::$calls=[];
$cp->request('POST',"/services/commande/$id2/rembourser",['_token'=>$tkr],[],$H); $em=em($cp); $em->clear(); t('prestataire rembourse: remboursee', $em->find(ServiceOrder::class,$id2)->getStatus()==='remboursee' && in_array("refund:$id2",FakeGateway::$calls));
// prestataire non pret
$cn=client('m_noready@x.fr'); $cn->request('GET','/espace-acteur/offre/nouvelle'); t('offre sans stripe pret: redirige', $cn->getResponse()->isRedirect());
// creation offre
$cr=$cp->request('GET','/espace-acteur/offre/nouvelle'); $f=$cr->selectButton('Envoyer pour relecture')->form(['title'=>'Formation civique','description'=>str_repeat('Atelier pratique sur les institutions. ',4),'price'=>'45,50','category'=>'formation','no_political'=>true]); $cp->submit($f);
$no=em($cp)->getRepository(ServiceOffer::class)->findOneBy(['title'=>'Formation civique']); t('offre creee en attente a 4550', $no && $no->getStatus()==='En attente' && $no->getPriceCents()===4550);
$cr=$cp->request('GET','/espace-acteur/offre/nouvelle'); $f=$cr->selectButton('Envoyer pour relecture')->form(['title'=>'Formation connard','description'=>str_repeat('Atelier pratique sur les institutions. ',4),'price'=>'45','category'=>'formation']); $cp->submit($f); t('insulte refusee', em($cp)->getRepository(ServiceOffer::class)->count(['title'=>'Formation connard'])===0);
$cr=$cp->request('GET','/espace-acteur/offre/nouvelle'); $f=$cr->selectButton('Envoyer pour relecture')->form(['title'=>'Prix trop bas ici','description'=>str_repeat('Atelier pratique sur les institutions. ',4),'price'=>'1','category'=>'formation']); $cp->submit($f); t('prix hors bornes refuse', em($cp)->getRepository(ServiceOffer::class)->count(['title'=>'Prix trop bas ici'])===0);
// admin
$cu=client('m_buy@x.fr'); $cu->request('GET','/admin/services'); t('admin services 403 membre',$cu->getResponse()->getStatusCode()===403);
$ca=client('m_adm@x.fr'); $cr=$ca->request('GET','/admin/services'); t('admin services 200',$ca->getResponse()->getStatusCode()===200);
$nid=$no->getId(); $tka=$cr->filterXPath("//button[contains(@formaction,'/$nid/approve')]/ancestor::form//input[@name='_token']")->first()->attr('value');
$ca->request('POST',"/admin/services/$nid/approve",['_token'=>$tka],[],$H); $em=em($ca); $em->clear(); t('admin publie', $em->find(ServiceOffer::class,$nid)->getStatus()==='Publiée');

// messagerie (commande $oidd terminee, $id2 remboursee) -> on cree une commande payee
$cb=client('m_buy@x.fr'); $cr=$cb->request('GET',"/services/$oid"); $t3=$cr->filter('main input[name=_token]')->first()->attr('value');
$cb->request('POST',"/services/$oid/commander",['_token'=>$t3,'cgv'=>1,'no_political'=>1],[],$H); $o3=em($cb)->getRepository(ServiceOrder::class)->findOneBy(['status'=>'attente_paiement']); $id3=$o3->getId();
$mm=client('m_buy@x.fr'); $mm->request('GET',"/services/commande/$id3/messages"); $cr=$mm->getCrawler(); t('messagerie impossible avant paiement (pas de formulaire)', $mm->getResponse()->getStatusCode()===200 && !str_contains($mm->getResponse()->getContent(),'name="body"'));
$e3=json_encode(['type'=>'checkout.session.completed','data'=>['object'=>['id'=>'cs_fake_'.$id3,'payment_status'=>'paid','payment_intent'=>'pi_3','amount_total'=>10001]]]); $w=client(); $w->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>sig($e3)],$e3);
$cr=$mm->request('GET',"/services/commande/$id3/messages"); $tm=$cr->filter('main input[name=_token]')->first()->attr('value');
$send=function($cl,$b,$tk) use($id3,$H){ $cl->request('POST',"/services/commande/$id3/messages",['_token'=>$tk,'body'=>$b],[],$H); return em($cl)->getRepository(App\Entity\OrderMessage::class)->count([]); };
t('message normal accepte', $send($mm,'Bonjour, je souhaite une séance samedi matin.',$tm)===1);
t('email bloque', $send($mm,'ecrivez moi a jean.dupont@gmail.com',$tm)===1);
t('telephone bloque', $send($mm,'appelez le 06 12 34 56 78',$tm)===1);
t('lien bloque', $send($mm,'voir https://exemple.fr/x',$tm)===1);
t('whatsapp bloque', $send($mm,'on continue sur whatsapp',$tm)===1);
t('insulte bloquee', $send($mm,'espèce de connard',$tm)===1);
t('csrf invalide bloque', $send($mm,'message valide ici','bad')===1);
$pp=client('m_prov@x.fr'); $cr=$pp->request('GET',"/services/commande/$id3/messages"); t('prestataire voit le message', str_contains($pp->getResponse()->getContent(),'samedi matin')); $tp2=$cr->filter('main input[name=_token]')->first()->attr('value');
t('prestataire repond', $send($pp,'Très bien, samedi 10h convient.',$tp2)===2);
$oo=client('m_other@x.fr'); $oo->request('GET',"/services/commande/$id3/messages"); t('tiers 403 lecture',$oo->getResponse()->getStatusCode()===403);
$oo->request('POST',"/services/commande/$id3/messages",['_token'=>'x','body'=>'coucou'],[],$H); t('tiers 403 ecriture',$oo->getResponse()->getStatusCode()===403);
$aa=client('m_adm@x.fr'); $aa->request('GET',"/services/commande/$id3/messages"); t('admin lit (litige)',$aa->getResponse()->getStatusCode()===200 && !str_contains($aa->getResponse()->getContent(),'name="body"'));
$aa->request('POST',"/services/commande/$id3/messages",['_token'=>'x','body'=>'intrusion'],[],$H); t('admin ne peut pas ecrire',$aa->getResponse()->getStatusCode()===403);
// hub
$h=client(); $h->request('GET','/mon-espace'); t('hub anonyme redirige',$h->getResponse()->isRedirect());
$h=client('m_buy@x.fr'); $h->request('GET','/mon-espace'); $r=$h->getResponse()->getContent(); t('hub membre: pas d\'admin ni acteur', $h->getResponse()->getStatusCode()===200 && !str_contains($r,'Administration') && !str_contains($r,'Mon espace acteur') && str_contains($r,'Trouver un service'));
$h=client('m_prov@x.fr'); $h->request('GET','/mon-espace'); t('hub acteur: espace acteur', str_contains($h->getResponse()->getContent(),'Mon espace acteur'));
$h=client('m_adm@x.fr'); $h->request('GET','/mon-espace'); t('hub admin: administration', str_contains($h->getResponse()->getContent(),'Administration'));
echo "$ok ok / $ko ko\n";
