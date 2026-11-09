<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Debat,Dossier,MissionPost,MissionApplication};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();$h=$c->getContainer()->get('security.user_password_hasher');
function mk($em,$h,$role,$first){$u=new User();$u->setEmail('s28'.uniqid().'@test.fr');$u->setPassword($h->hashPassword($u,'x'));$u->setFirstName($first);$u->setLastName('Martin');if($role)$u->setRoles([$role]);$em->persist($u);$em->flush();return $u;}
function tok($c,$url,$name){ $c->request('GET',$url); return $c->getResponse()->getContent(); }
$a=mk($em,$h,null,'Alice');$b=mk($em,$h,null,'Bruno');$adm=mk($em,$h,'ROLE_ADMIN','Admin');
// reset
$c->request('GET','/reinitialisation-mot-de-passe');$st=$c->getResponse()->getStatusCode();
if($st==404){$c->request('GET','/reset-password');$st=$c->getResponse()->getStatusCode();}
t('page reset 200',$st==200 && str_contains($c->getResponse()->getContent(),'Mot de passe oublié'));
// missions annonces
$c->request('GET','/missions/annonces');t('annonces 200',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/missions/annonces/nouvelle');t('nouvelle anonyme redirige',in_array($c->getResponse()->getStatusCode(),[301,302,303]));
$c->loginUser($a);
$c->request('GET','/missions/annonces/nouvelle?type=offre');$html=$c->getResponse()->getContent();
preg_match('/name="_token" value="([^"]+)"/',$html,$m);
$post=['_token'=>$m[1],'kind'=>'demande','title'=>'Relire une traduction anglaise '.uniqid(),'description'=>'Je cherche quelqu un pour relire la traduction anglaise d un débat sur la santé, une heure suffit.','territory'=>'Nantes','pay'=>'free'];
$c->request('POST','/missions/annonces/nouvelle',$post);t('annonce creee',in_array($c->getResponse()->getStatusCode(),[302,303]));
$p=$em->getRepository(MissionPost::class)->findOneBy(['author'=>$a]);
t('annonce en attente',$p&&$p->getStatus()==='pending');
$c->request('GET','/missions/annonces');t('non publiee invisible',!str_contains($c->getResponse()->getContent(),$post['title'])||str_contains($c->getResponse()->getContent(),'Mes annonces'));
$bad=$post;$bad['title']='Titre avec contact '.uniqid();$bad['description']='Appelez moi au 06 12 34 56 78 pour discuter de la mission proposée ici, merci beaucoup.';
$c->request('POST','/missions/annonces/nouvelle',$bad);t('telephone refuse',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'ni e-mail ni téléphone'));
// moderation
$c->loginUser($adm);
$c->request('GET','/admin/moderation');t('moderation liste annonce',str_contains($c->getResponse()->getContent(),$post['title']));
preg_match('#annonce-mission/'.$p->getId().'/approve"[^>]*><input type="hidden" name="_token" value="([^"]+)"#',$c->getResponse()->getContent(),$mm);
$c->request('POST','/admin/moderation/annonce-mission/'.$p->getId().'/approve',['_token'=>$mm[1]??'']);
$em->clear();$p=$em->getRepository(MissionPost::class)->find($p->getId());
t('annonce publiee',$p->getStatus()==='published');
// reponse
$c->loginUser($b);
$c->request('GET','/missions/annonces');t('annonce visible',str_contains($c->getResponse()->getContent(),$post['title']));
$c->request('GET','/missions/annonces/'.$p->getId().'/repondre');preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST','/missions/annonces/'.$p->getId().'/repondre',['_token'=>$m[1],'email'=>'b@ex.fr','phone'=>'06 12 34 56 78','message'=>'Je suis bilingue et je peux relire cette traduction dès demain soir sans souci.']);
t('reponse envoyee',in_array($c->getResponse()->getStatusCode(),[302,303])&&$em->getRepository(MissionApplication::class)->count(['applicant'=>$b])===1);
$c->loginUser($a);
$c->request('GET','/espace-acteur/candidatures');t('auteur voit la reponse',$c->getResponse()->getStatusCode()==200);
// theme libre + dossier inline
$c->request('GET','/debats/ouvrir');t('form ouvrir 200',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'__autre'));
preg_match('/id="debat-form".*?name="_token" value="([^"]+)"/s',$c->getResponse()->getContent(),$m);
$title='Comment mieux aider les agriculteurs bio '.uniqid();
$dt='Agriculture biologique '.uniqid();
$deb=['_token'=>$m[1],'title'=>$title,'theme'=>'__autre','theme_other'=>'Agriculture bio','dossier'=>'__nouveau','dossier_title'=>$dt,'summary'=>str_repeat('La filière bio traverse une période difficile, avec des prix en baisse et des aides inégales. ',3),'facts'=>'Chiffres de l\'Agence Bio : https://www.agencebio.org/vos-outils/les-chiffres-cles/ et https://agreste.agriculture.gouv.fr/','authority'=>''];
$c->request('POST','/debats/ouvrir',$deb);t('debat theme libre cree',in_array($c->getResponse()->getStatusCode(),[302,303]));
$d=$em->getRepository(Debat::class)->findOneBy(['title'=>$title]);
t('theme libre stocke',$d&&$d->getTheme()==='Agriculture bio');
$nd=$em->getRepository(Dossier::class)->findOneBy(['title'=>$dt]);
t('dossier cree en attente',$nd&&$nd->getStatus()==='pending'&&$d->getDossierSlug()===$nd->getSlug());
$bad=$deb;$bad['title'].='x';$bad['theme_other']='Pas <script>';
$c->request('POST','/debats/ouvrir',$bad);t('theme libre invalide refuse',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Le thème libre'));
$em->getConnection()->executeStatement('UPDATE debat SET status=? WHERE id=?',[Debat::STATUS_OPEN,$d->getId()]);$em->clear();
$c->request('GET','/debats?theme='.urlencode('Agriculture bio'));t('filtre theme libre',str_contains($c->getResponse()->getContent(),$title));
// sitemap https n/a en test
echo "\n$ok OK / $ko KO\n";
$c->request('GET','/debats?theme='.urlencode('Agriculture bio'));$x=$c->getResponse()->getContent();echo $c->getResponse()->getStatusCode(),' ',substr_count($x,'Agriculture'),' ',var_export(str_contains($x,'agriculteurs'),true),' ',$d->getStatus(),"\n";
echo "theme en base: [".$d->getTheme()."] n=".count($em->getRepository(Debat::class)->findPublic('Agriculture bio'))." custom=".json_encode($em->getRepository(Debat::class)->customThemes())."\n";
