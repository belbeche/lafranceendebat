<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Debat,Dossier,Proposition};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();$h=$c->getContainer()->get('security.user_password_hasher');
function mk($em,$h,$role,$first){$u=new User();$u->setEmail('dos'.uniqid().'@test.fr');$u->setPassword($h->hashPassword($u,'x'));$u->setFirstName($first);$u->setLastName('Durand');if($role)$u->setRoles([$role]);$em->persist($u);$em->flush();return $u;}
$u=mk($em,$h,null,'Camille');$mod=mk($em,$h,'ROLE_ADMIN','Admin');
$cat=$c->getContainer()->get(App\Service\DossierCatalog::class);
t('15 dossiers de depart',count($cat->starters())===15);
t('un dossier par theme',count(array_diff(App\Entity\Proposition::THEMES,array_map(fn($s)=>$s[3],$cat->starters())))===0);
$c->request('GET','/dossiers');t('index 200 + starters',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Un dossier de départ par thème'));
$slug=array_key_first($cat->starters());
$c->request('GET','/dossiers/'.$slug);t('dossier de depart 200',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Sources officielles'));
$c->request('GET','/dossiers/education');t('dossier education toujours 200',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/dossiers/inexistant');t('inconnu 404',$c->getResponse()->getStatusCode()==404);
$c->request('GET','/dossiers/proposer');t('proposer anonyme redirige',in_array($c->getResponse()->getStatusCode(),[301,302,303]));
$c->loginUser($u);
// proposer
$c->request('GET','/dossiers/proposer');t('proposer connecte 200',$c->getResponse()->getStatusCode()==200);
$f=$c->getCrawler()->filter('form.form-card')->form();
$title='Dossier citoyen sur la cantine '.uniqid();
$vals=$f->getPhpValues();$vals['title']=$title;$vals['theme']='Santé';$vals['intro']='Un resume factuel de la restauration scolaire en France.';$vals['body']=str_repeat('Texte factuel sur la restauration scolaire. ',8);$vals['sources']="https://www.education.gouv.fr/cantine\nhttps://www.insee.fr/fr/accueil";
$c->request('POST','/dossiers/proposer',$vals);t('creation redirige',in_array($c->getResponse()->getStatusCode(),[302,303]));
$d=$em->getRepository(Dossier::class)->findOneBy(['title'=>$title]);
t('dossier en attente',$d&&$d->getStatus()==='pending'&&$d->getAuthor()->getId()===$u->getId());
$c->request('GET','/dossiers/'.$d->getSlug());t('auteur voit son dossier en attente',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'En attente de validation'));
// insulte
$v2=$vals;$v2['title']='Dossier hostile '.uniqid();$v2['body']='Ces cons sont tous des idiots. '.str_repeat('x ',120);
$c->request('POST','/dossiers/proposer',$v2);t('insulte bloquee',$c->getResponse()->getStatusCode()==200&&!$em->getRepository(Dossier::class)->findOneBy(['title'=>$v2['title']]));
// une seule source
$v3=$vals;$v3['title']='Dossier une source '.uniqid();$v3['sources']='https://www.insee.fr';
$c->request('POST','/dossiers/proposer',$v3);t('deux sources exigees',!$em->getRepository(Dossier::class)->findOneBy(['title'=>$v3['title']]));
// autre utilisateur ne voit pas
$u2=mk($em,$h,null,'Lea');$c->loginUser($u2);
$c->request('GET','/dossiers/'.$d->getSlug());t('autre utilisateur 404 tant que non valide',$c->getResponse()->getStatusCode()==404);
// debat sans dossier refuse
$c->loginUser($u);
$c->request('GET','/debats/ouvrir');t('form debat 200 avec choix dossier',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Dossier de référence'));
$f=$c->getCrawler()->filter('form.form-card')->form();$base=$f->getPhpValues();
$dv=array_merge($base,['title'=>'Faut-il changer les menus de cantine '.uniqid().' ?','theme'=>'Santé','summary'=>str_repeat('Exposé neutre du sujet. ',8),'facts'=>'Données publiques disponibles sur https://www.insee.fr/fr/accueil pour vérification.','dossier'=>'']);
$c->request('POST','/debats/ouvrir',$dv);t('debat sans dossier refuse',$c->getResponse()->getStatusCode()==200&&!$em->getRepository(Debat::class)->findOneBy(['title'=>$dv['title']]));
$dv['dossier']='slug-bidon';$c->request('POST','/debats/ouvrir',$dv);t('dossier invalide refuse',!$em->getRepository(Debat::class)->findOneBy(['title'=>$dv['title']]));
$dv['dossier']=$d->getSlug();$c->request('POST','/debats/ouvrir',$dv);
$deb=$em->getRepository(Debat::class)->findOneBy(['title'=>$dv['title']]);
t('debat cree avec dossier en validation',$deb&&$deb->getDossierSlug()===$d->getSlug());
// moderation
$c->loginUser($mod);
$c->request('GET','/admin/moderation');t('file de moderation liste le dossier',str_contains($c->getResponse()->getContent(),$title));
$tok=$c->getCrawler()->filter('form[action$="/dossier/'.$d->getId().'/approve"] input[name=_token]')->attr('value');
$c->request('POST','/admin/moderation/dossier/'.$d->getId().'/approve',['_token'=>$tok]);
$em->clear();$d=$em->getRepository(Dossier::class)->find($d->getId());
t('dossier publie',$d->isPublished());
$c->request('GET','/dossiers/'.$d->getSlug());t('public voit le dossier signe',str_contains($c->getResponse()->getContent(),'Camille D.'));
$c->request('GET','/dossiers');t('liste contient dossier citoyen',str_contains($c->getResponse()->getContent(),$title));
// identite sur debat publie
$deb=$em->getRepository(Debat::class)->find($deb->getId());$deb->setStatus(Debat::STATUS_OPEN);$em->flush();
$c->request('GET','/debats/'.$deb->getSlug());$html=$c->getResponse()->getContent();
t('debat affiche ouvert par + dossier',str_contains($html,'Débat ouvert par <strong>Camille D.</strong>')&&str_contains($html,$title));
// refus
$d2=(new Dossier())->setAuthor($em->getRepository(User::class)->find($u->getId()))->setTitle('A refuser '.uniqid())->setSlug('a-refuser-'.uniqid())->setIntro(str_repeat('i',40))->setBody(str_repeat('b',210))->setSources("https://a.fr/x\nhttps://b.fr/y");$em->persist($d2);$em->flush();
$c->request('GET','/admin/moderation');$tok=$c->getCrawler()->filter('form[action$="/dossier/'.$d2->getId().'/reject"] input[name=_token]')->attr('value');
$c->request('POST','/admin/moderation/dossier/'.$d2->getId().'/reject',['_token'=>$tok,'note'=>'Sources insuffisantes']);
$em->clear();t('dossier refuse avec motif',$em->getRepository(Dossier::class)->find($d2->getId())->getStatus()==='rejected');
// proposition : formulaire exige dossier
$c->loginUser($u);
$c->request('GET','/propositions/nouvelle');t('form proposition a le choix du dossier',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Dossier de référence'));
echo "$ok ok / $ko ko\n";
