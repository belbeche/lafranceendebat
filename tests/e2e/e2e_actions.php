<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Debat,Event};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
function mk($em,$roles){$u=new User();$u->setEmail('a'.uniqid().'@orga-test.fr')->setPassword('x')->setRoles($roles);foreach(['setFirstName','setLastName'] as $m)if(method_exists($u,$m))$u->$m('T'.uniqid());$em->persist($u);$em->flush();return $u;}
$cit=mk($em,['ROLE_USER']);$elu=mk($em,['ROLE_USER','ROLE_ELU']);
// pages avec panneau d'actions
foreach(['/debats','/revendications','/candidats','/services','/scrutins','/agenda'] as $u){$c->request('GET',$u);$st=$c->getResponse()->getStatusCode();t("$u $st",in_array($st,[200,301]));}
$c->request('GET','/debats');t('visiteur: pas de bouton ouvrir',!str_contains($c->getResponse()->getContent(),'Ouvrir un débat'));
$c->loginUser($cit);$c->request('GET','/debats');t('citoyen: bouton Ouvrir un débat',str_contains($c->getResponse()->getContent(),'Ouvrir un débat'));
$c->request('GET','/debats/ouvrir');$h=$c->getResponse()->getContent();t('formulaire debat',$c->getResponse()->getStatusCode()==200);
preg_match('/id="debat-form".*?name="_token" value="([^"]+)"/s',$h,$m);$tok=$m[1];
$c->request('POST','/debats/ouvrir',['_token'=>$tok,'title'=>'Court','theme'=>'Santé','summary'=>'x','facts'=>'y']);t('debat invalide refuse',$em->getRepository(Debat::class)->count(['proposedBy'=>$cit])==0);
$c->request('POST','/debats/ouvrir',['_token'=>$tok,'title'=>'Comment financer les urgences de proximité ?','theme'=>'Santé','summary'=>str_repeat('Les urgences de proximité ferment faute de personnel et de financement stable. ',3),'dossier'=>'theme-sante','facts'=>'Rapport public 2024 : https://www.ccomptes.fr/fr/publications exemple de source officielle.']);
$d=$em->getRepository(Debat::class)->findOneBy(['proposedBy'=>$cit]);t('debat cree en brouillon',$d&&$d->getStatus()==='draft');
$c->request('GET','/debats');t('brouillon invisible',!str_contains($c->getResponse()->getContent(),'urgences de proximité'));
$c->request('GET','/mes-demandes');t('mes demandes liste le debat',str_contains($c->getResponse()->getContent(),'En relecture')&&str_contains($c->getResponse()->getContent(),'urgences'));
// agenda: citoyen refuse, acteur ok
$c->request('GET','/agenda/proposer');t('citoyen redirige',$c->getResponse()->isRedirect());
$c->loginUser($elu);$c->request('GET','/agenda');t('acteur: bouton proposer',str_contains($c->getResponse()->getContent(),'Proposer une rencontre'));
$c->request('GET','/agenda/proposer');$h=$c->getResponse()->getContent();t('formulaire rencontre',$c->getResponse()->getStatusCode()==200);preg_match('/name="_token" value="([^"]+)"/',$h,$m);
$c->request('POST','/agenda/proposer',['_token'=>$m[1],'name'=>'Permanence citoyenne de la mairie','kind'=>'permanence','date'=>(new DateTime('+10 days'))->format('Y-m-d\TH:i'),'location'=>'Mairie','description'=>'Une permanence pour recueillir les demandes des contribuables de la commune, sur rendez-vous ou sans.']);
$e=$em->getRepository(Event::class)->findOneBy(['proposedBy'=>$elu]);t('rencontre en attente',$e&&$e->getStatus()==='pending');
$c->request('GET','/agenda');t('rencontre non publiee invisible',!str_contains($c->getResponse()->getContent(),'Permanence citoyenne de la mairie'));
$c->request('GET','/services');t('services: acteur sans ROLE_ACTOR pas de bouton publier',!str_contains($c->getResponse()->getContent(),'Publier un service'));
echo "$ok ok / $ko ko\n";
