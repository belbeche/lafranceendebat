<?php
// MAIL_FROM=noreply@lafranceendebat.fr php tests/e2e/e2e_argmanage.php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Debat,Argument,Proposition};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$GLOBALS['sent']=[];
$k->getContainer()->get('test.service_container')->get('event_dispatcher')->addListener(Symfony\Component\Mailer\Event\MessageEvent::class,function($e){$m=$e->getMessage();if($m instanceof Symfony\Component\Mime\Email)$GLOBALS['sent'][]=[implode(',',array_map(fn($a)=>$a->getAddress(),$m->getTo())),$m->getSubject(),$m->getHeaders()->has('List-Unsubscribe')];});
$mk=function($role,$p,$f=null)use($em){$u=new User();$u->setEmail($p.uniqid().'@orga-test.fr')->setPassword('x')->setRoles($role);$u->setFirstName($p);$u->setLastName('T');if($f)$f($u);$em->persist($u);$em->flush();return $u;};
$own=$mk(['ROLE_USER'],'own');$au=$mk(['ROLE_USER'],'aut');$ot=$mk(['ROLE_USER'],'oth');$mo=$mk(['ROLE_MODERATOR'],'mod');
$sub=$mk(['ROLE_USER'],'sub',fn($u)=>$u->setNotifyNewDebates(true));$off=$mk(['ROLE_USER'],'off',fn($u)=>$u->setNotifyOwnActivity(false));
$d=(new Debat())->setTitle('Débat gestion arguments')->setSlug('garg-'.uniqid())->setTheme(Proposition::THEMES[0])->setSummary(str_repeat('Exposé neutre. ',10))->setFacts('Source https://www.insee.fr/fr/statistiques test faits')->setStatus('open')->setProposedBy($off);
$em->persist($d);$a=(new Argument())->setDebat($d)->setAuthor($au)->setStance('pour')->setContent('Argument initial documenté sur la question posée.');$em->persist($a);$em->flush();
$u='/debats/'.$d->getSlug().'/arguments/'.$a->getId();
$c->loginUser($ot);$c->request('GET',$u.'/modifier');t('autre utilisateur refuse (redirige)',$c->getResponse()->isRedirect());
$c->loginUser($au);$c->request('GET','/debats/'.$d->getSlug());$h=$c->getResponse()->getContent();t('boutons modifier/retirer visibles pour l auteur',str_contains($h,'/modifier')&&str_contains($h,'Confirmer le retrait'));
$c->request('GET',$u.'/modifier');$h=$c->getResponse()->getContent();t('formulaire edition',$c->getResponse()->getStatusCode()==200);preg_match('/name="_token" value="([^"]+)"/',$h,$m);
$c->request('POST',$u.'/modifier',['_token'=>$m[1],'content'=>'Argument corrigé dans le délai de grâce, toujours sourcé.','sourceUrl'=>'','reformulation'=>'']);$em->clear();$a=$em->getRepository(Argument::class)->find($a->getId());
t('edition appliquee',str_contains($a->getContent(),'corrigé'));t('delai de grace: pas marque modifie',$a->getUpdatedAt()===null&&$a->getEditCount()===0);
$ref=new ReflectionProperty(Argument::class,'createdAt');$ref->setAccessible(true);$ref->setValue($a,new DateTimeImmutable('-1 hour'));$em->flush();
$c->request('POST',$u.'/modifier',['_token'=>$m[1],'content'=>'Argument modifié une heure après, avec une source officielle.','sourceUrl'=>'https://www.insee.fr/x','reformulation'=>'']);$em->clear();$a=$em->getRepository(Argument::class)->find($a->getId());
t('modification tardive marquee',$a->getUpdatedAt()!==null&&$a->getEditCount()===1);
$c->request('GET','/debats/'.$d->getSlug());t('page: modifié le',str_contains($c->getResponse()->getContent(),'modifié le'));
$c->request('POST',$u.'/modifier',['_token'=>$m[1],'content'=>str_repeat('Texte ',10).' espèce de connard','sourceUrl'=>'','reformulation'=>'']);$em->clear();t('insulte refusee a l edition',!str_contains($em->getRepository(Argument::class)->find($a->getId())->getContent(),'connard'));
$c->loginUser($mo);$c->request('GET',$u.'/modifier');t('moderateur peut modifier',$c->getResponse()->getStatusCode()==200);
$c->loginUser($ot);$c->request('POST',$u.'/retirer',['_token'=>'x']);t('autre ne peut pas retirer',$c->getResponse()->getStatusCode()==403);
$c->loginUser($au);$c->request('GET',$u.'/modifier');preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('GET','/debats/'.$d->getSlug());preg_match('~arguments/'.$a->getId().'/retirer"[^>]*><input type="hidden" name="_token" value="([^"]+)"~',$c->getResponse()->getContent(),$mw);
$c->request('POST',$u.'/retirer',['_token'=>'faux']);$em->clear();t('retrait sans jeton valide refuse',$em->getRepository(Argument::class)->find($a->getId())->isVisible());
$c->request('POST',$u.'/retirer',['_token'=>$mw[1]??'']);$em->clear();t('retrait par l auteur',!$em->getRepository(Argument::class)->find($a->getId())->isVisible());
$c->request('GET','/debats/'.$d->getSlug());t('argument retire non affiche',!str_contains($c->getResponse()->getContent(),'une heure après'));
// preferences
$c->loginUser($ot);$c->request('GET','/profil/preferences-email');t('page preferences',$c->getResponse()->getStatusCode()==200);preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST','/profil/preferences-email',['_token'=>$m[1],'debates'=>'1']);$em->clear();$o=$em->getRepository(User::class)->find($ot->getId());t('preferences enregistrees (debats on, activite off)',$o->isNotifyNewDebates()&&!$o->isNotifyOwnActivity());
// desabonnement signe
$signer=$k->getContainer()->get('test.service_container')->get('uri_signer');$url=$signer->sign('http://localhost/desabonnement-email/'.$ot->getId());
$c->loginUser($au);$c->request('GET','/desabonnement-email/'.$ot->getId());t('lien non signe refuse',$c->getResponse()->getStatusCode()==404);
$c->request('GET',$url);$em->clear();$o=$em->getRepository(User::class)->find($ot->getId());t('desabonnement en un clic',$c->getResponse()->getStatusCode()==200&&!$o->isNotifyNewDebates());
// nouveau debat -> abonnes seulement
$own=$em->getRepository(User::class)->find($own->getId());$GLOBALS["sent"]=[];$d2=(new Debat())->setTitle('Autre débat pour abonnés')->setSlug('abo-'.uniqid())->setTheme(Proposition::THEMES[0])->setSummary(str_repeat('Exposé neutre. ',10))->setFacts('Source https://www.insee.fr/fr/statistiques test faits')->setStatus('draft')->setProposedBy($own);$em->persist($d2);$em->flush();
$ad=$mk(['ROLE_ADMIN'],'adm');$c->loginUser($ad);$p='/debats/'.$d2->getSlug().'/gerer';$c->request('GET',$p);preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST',$p,['_token'=>$m[1],'title'=>$d2->getTitle(),'theme'=>$d2->getTheme(),'summary'=>$d2->getSummary(),'facts'=>$d2->getFacts(),'authority'=>'','status'=>'open','closesAt'=>'','outcome'=>'']);
$to=fn($e,$s)=>count(array_filter($GLOBALS['sent'],fn($x)=>$x[0]===$e&&str_contains($x[1],$s)))>0;
t('abonne prevenu du nouveau debat',$to($sub->getEmail(),'Nouveau débat'));t('non abonne pas prevenu',!$to($au->getEmail(),'Nouveau débat'));
t('en-tete List-Unsubscribe',count(array_filter($GLOBALS['sent'],fn($x)=>$x[0]===$sub->getEmail()&&$x[2]))>0);
// activite coupee : l auteur du debat (off) ne recoit pas d email d argument
$GLOBALS['sent']=[];$c->loginUser($ot);
$c->request('GET','/debats/'.$d->getSlug());preg_match('/name="argument\[_token\]" value="([^"]+)"/',$c->getResponse()->getContent(),$tk);
$c->request('POST','/debats/'.$d->getSlug().'/arguments',['argument'=>['stance'=>'pour','content'=>'Un nouvel argument documenté pour tester les préférences.','sourceUrl'=>'','charter'=>'1','_token'=>$tk[1]??'']]);
t('activite desactivee: aucun e-mail a l auteur du debat',!$to($off->getEmail(),'argument'));
echo "$ok ok / $ko ko\n";
