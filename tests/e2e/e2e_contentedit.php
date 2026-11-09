<?php
// MAIL_FROM=noreply@lafranceendebat.fr php tests/e2e/e2e_contentedit.php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Proposition,Discussion,DiscussionReply,Revendication,NewsletterSubscriber};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$GLOBALS['sent']=[];
$k->getContainer()->get('test.service_container')->get('event_dispatcher')->addListener(Symfony\Component\Mailer\Event\MessageEvent::class,function($e){$m=$e->getMessage();if($m instanceof Symfony\Component\Mime\Email)$GLOBALS['sent'][]=[implode(',',array_map(fn($a)=>$a->getAddress(),$m->getTo())),$m->getSubject()];});
$mk=function($role,$p)use($em){$u=new User();$u->setEmail($p.uniqid().'@orga-test.fr')->setPassword('x')->setRoles($role);$u->setFirstName($p);$u->setLastName('T');$em->persist($u);$em->flush();return $u;};
$au=$mk(['ROLE_USER'],'aut');$ot=$mk(['ROLE_USER'],'oth');$mo=$mk(['ROLE_MODERATOR'],'mod');
$old=new DateTimeImmutable('-2 hours');
$setCreated=function($e,$cls,$prop='createdAt')use($old,$em){$r=new ReflectionProperty($cls,$prop);$r->setAccessible(true);$r->setValue($e,$old);};
function token($c,$url,$field='_token'){ $c->request('GET',$url);preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);return $m[1]??'';}
// --- proposition
$p=(new Proposition())->setTitle('Une proposition citoyenne de test')->setTheme(Proposition::THEMES[0])->setDescription(str_repeat('Mesure proposée documentée. ',4))->setAuthor($au)->setStatus('Validée');$em->persist($p);$em->flush();$setCreated($p,Proposition::class);$em->flush();
$u='/propositions/'.$p->getId().'/modifier';
$c->loginUser($ot);$c->request('GET',$u);t('proposition: autre utilisateur 403',$c->getResponse()->getStatusCode()==403);
$c->loginUser($au);$tk=token($c,$u);t('proposition: formulaire auteur',$c->getResponse()->getStatusCode()==200);
$c->request('POST',$u,['_token'=>$tk,'title'=>'Une proposition citoyenne corrigée','theme'=>$p->getTheme(),'description'=>str_repeat('Mesure corrigée et documentée. ',4),'sourceUrl'=>'']);$em->clear();$p=$em->getRepository(Proposition::class)->find($p->getId());
t('proposition modifiee + marquee',$p->getTitle()==='Une proposition citoyenne corrigée'&&$p->getEditCount()===1&&$p->getEditedAt()!==null);
$c->request('GET','/propositions/'.$p->getId());t('page: modifié le + boutons',str_contains($c->getResponse()->getContent(),'modifié le')&&str_contains($c->getResponse()->getContent(),'Confirmer le retrait'));
$c->request('POST',$u,['_token'=>$tk,'title'=>'Titre avec insulte','theme'=>$p->getTheme(),'description'=>str_repeat('Texte ',15).' espèce de connard','sourceUrl'=>'']);$em->clear();t('proposition: insulte refusee',!str_contains($em->getRepository(Proposition::class)->find($p->getId())->getDescription(),'connard'));
$c->request('GET','/propositions/'.$p->getId());preg_match('~propositions/'.$p->getId().'/retirer"[^>]*><input type="hidden" name="_token" value="([^"]+)"~',$c->getResponse()->getContent(),$mw);
$c->request('POST','/propositions/'.$p->getId().'/retirer',['_token'=>'faux']);$em->clear();t('proposition: retrait sans jeton refuse',$em->getRepository(Proposition::class)->find($p->getId())->isValidated());
$c->request('POST','/propositions/'.$p->getId().'/retirer',['_token'=>$mw[1]??'']);$em->clear();t('proposition retiree',$em->getRepository(Proposition::class)->find($p->getId())->getStatus()==='Retirée');
$c->loginUser($ot);$c->request('POST','/propositions/'.$p->getId().'/retirer',['_token'=>'x']);t('proposition: un autre ne peut pas retirer',$c->getResponse()->getStatusCode()==403);
// --- discussion + reponse
$au=$em->getRepository(User::class)->find($au->getId());$d=(new Discussion())->setTitle('Une discussion de test')->setBody(str_repeat('Un message de discussion utile. ',3))->setCategory('dialogue')->setAuthor($au)->setStatus('Validée');$em->persist($d);$em->flush();$setCreated($d,Discussion::class);$em->flush();
$c->loginUser($au);$u='/communaute/'.$d->getId().'/modifier';$tk=token($c,$u);
$c->request('POST',$u,['_token'=>$tk,'title'=>'Une discussion corrigée','body'=>str_repeat('Message corrigé, toujours utile. ',3)]);$em->clear();$d=$em->getRepository(Discussion::class)->find($d->getId());
t('discussion modifiee + marquee',$d->getTitle()==='Une discussion corrigée'&&$d->getEditCount()===1);
$au=$em->getRepository(User::class)->find($au->getId());
$r=(new DiscussionReply())->setDiscussion($d)->setAuthor($au)->setBody(str_repeat('Une réponse argumentée. ',4));$em->persist($r);$em->flush();
$c->request('GET','/communaute/'.$d->getId());t('discussion: page avec modifier',str_contains($c->getResponse()->getContent(),'Modifier'));
$u='/communaute/reponse/'.$r->getId().'/modifier';$tk=token($c,$u);$c->request('POST',$u,['_token'=>$tk,'body'=>str_repeat('Réponse corrigée, argumentée. ',4)]);$em->clear();
t('reponse modifiee (delai de grace: non marquee)',str_contains($em->getRepository(DiscussionReply::class)->find($r->getId())->getBody(),'corrigée')&&$em->getRepository(DiscussionReply::class)->find($r->getId())->getEditCount()===0);
$c->request('POST','/communaute/'.$d->getId().'/retirer',['_token'=>'faux']);$em->clear();t('discussion: retrait sans jeton refuse',$em->getRepository(Discussion::class)->find($d->getId())->getStatus()==='Validée');
// --- revendication
$au=$em->getRepository(User::class)->find($au->getId());$mo=$em->getRepository(User::class)->find($mo->getId());
$rv=(new Revendication())->setTitle('Une demande de test')->setSlug('rv-'.uniqid())->setGroupLabel(Revendication::GROUPS[0])->setTheme(Proposition::THEMES[0])->setSummary(str_repeat('Constat documenté et précis. ',4))->setSourceUrl('https://www.insee.fr/x')->setSubmittedBy($au)->setIsPublished(true);
$em->persist($rv);$em->flush();$u='/revendications/'.$rv->getSlug().'/modifier';
$GLOBALS['sent']=[];$c->loginUser($au);$tk=token($c,$u);
$c->request('POST',$u,['_token'=>$tk,'title'=>'Une demande corrigée','summary'=>str_repeat('Constat corrigé et précis. ',4),'sourceLabel'=>'INSEE','sourceUrl'=>'http://pas-https.fr']);$em->clear();t('revendication: source http refusee',$em->getRepository(Revendication::class)->find($rv->getId())->getTitle()==='Une demande de test');
$c->request('POST',$u,['_token'=>$tk,'title'=>'Une demande corrigée','summary'=>str_repeat('Constat corrigé et précis. ',4),'sourceLabel'=>'INSEE','sourceUrl'=>'https://www.insee.fr/y']);$em->clear();$rv=$em->getRepository(Revendication::class)->find($rv->getId());
t('revendication modifiee et repassee en relecture',$rv->getTitle()==='Une demande corrigée'&&!$rv->isPublished());
t('admin prevenu de la nouvelle relecture',count(array_filter($GLOBALS['sent'],fn($x)=>str_contains($x[1],'Nouvelle revendication')))>=1);
// --- lettre: case a l inscription + preferences
$c->getCookieJar()->clear();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));$c->request('GET','/inscription');$h=$c->getResponse()->getContent();t('inscription: case lettre presente et decochee',str_contains($h,'name="registration_form[newsletter]"')&&!preg_match('/name="registration_form\[newsletter\]"[^>]*checked/',$h));
$em->clear();$ot=$em->getRepository(User::class)->find($ot->getId());$c->loginUser($ot);$tk=token($c,'/profil/preferences-email');
$GLOBALS['sent']=[];$c->request('POST','/profil/preferences-email',['_token'=>$tk,'lettre'=>'1','own'=>'1']);
$sub=$em->getRepository(NewsletterSubscriber::class)->findOneBy(['email'=>mb_strtolower($ot->getEmail())]);
t('lettre: abonne cree en attente + e-mail de confirmation',$sub&&null===$sub->getConfirmedAt()&&count(array_filter($GLOBALS['sent'],fn($x)=>$x[0]===$ot->getEmail()))>=1);

// inscription avec case lettre cochee, puis sans
foreach([true,false] as $want){
 $c->getCookieJar()->clear();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
 $c->request('GET','/inscription');preg_match('/name="registration_form\[_token\]" value="([^"]+)"/',$c->getResponse()->getContent(),$m);$e='lt'.uniqid().'@orga-test.fr';
 $d=['_token'=>$m[1]??'','profile'=>'citoyen','organisation'=>'','email'=>$e,'firstName'=>'Jean','lastName'=>'Test','phone'=>'0612345678','postalCode'=>'75001','plainPassword'=>'T-'.bin2hex(random_bytes(9)),'agreeCharter'=>'1','ageOk'=>'1'];
 if($want)$d['newsletter']='1';$GLOBALS['sent']=[];
 $c->request('POST','/inscription',['registration_form'=>$d]);
 $sub=$em->getRepository(NewsletterSubscriber::class)->findOneBy(['email'=>$e]);
 if($want){t('inscription avec case: abonne en attente + confirmation envoyee',$sub&&null===$sub->getConfirmedAt()&&count(array_filter($GLOBALS['sent'],fn($x)=>$x[0]===$e&&!str_contains($x[1],'Bienvenue')))>=1);}
 else t('inscription sans case: aucun abonne cree',!$sub);
}
echo "$ok ok / $ko ko\n";
if(getenv('DEBUG')){print_r(array_slice($GLOBALS['sent'],-4));$s=$em->getRepository(NewsletterSubscriber::class)->findBy([],['id'=>'DESC'],2);foreach($s as $x)echo $x->getEmail(),' ',$x->getConfirmedAt()?'conf':'noconf',"\n";}
