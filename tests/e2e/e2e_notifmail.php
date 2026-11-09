<?php
// Lancer avec : MAIL_FROM=noreply@lafranceendebat.fr php tests/e2e/e2e_notifmail.php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Debat,Argument,Proposition,Tribune,Signalement};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$mk=function($role,$p)use($em){$u=new User();$u->setEmail($p.uniqid().'@orga-test.fr')->setPassword('x')->setRoles($role);$u->setFirstName($p);$u->setLastName('T');$em->persist($u);$em->flush();return $u;};
$own=$mk(['ROLE_USER'],'own');$pro=$mk(['ROLE_USER'],'pro');$con=$mk(['ROLE_USER'],'con');$ad=$mk(['ROLE_ADMIN'],'adm');
$GLOBALS['sent']=[];
$k->getContainer()->get('test.service_container')->get('event_dispatcher')->addListener(Symfony\Component\Mailer\Event\MessageEvent::class,function($e){$m=$e->getMessage();if($m instanceof Symfony\Component\Mime\Email)$GLOBALS['sent'][]=[implode(',',array_map(fn($a)=>$a->getAddress(),$m->getTo())),$m->getSubject()];});
$mails=fn()=>$GLOBALS['sent'];
$to=function($addr,$needle)use($mails){foreach($mails() as [$t,$s]) if($t===$addr&&str_contains($s,$needle)) return true;return false;};
$R=function(...$a)use($c,&$mails){$c->request(...$a);$mails();};
$d=(new Debat())->setTitle('Débat notifications test')->setSlug('notif-'.uniqid())->setTheme(Proposition::THEMES[0])->setSummary(str_repeat('Exposé neutre. ',10))->setFacts('Source https://www.insee.fr/fr/statistiques test faits')->setStatus('draft')->setProposedBy($own);
$em->persist($d);$em->flush();
// statut draft -> open par l'admin (ecran de gestion)
$c->loginUser($ad);$u='/debats/'.$d->getSlug().'/gerer';$R('GET',$u);preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$R('POST',$u,['_token'=>$m[1],'title'=>$d->getTitle(),'theme'=>$d->getTheme(),'summary'=>$d->getSummary(),'facts'=>$d->getFacts(),'authority'=>'','status'=>'open','closesAt'=>'','outcome'=>'']);
t('auteur prevenu a la publication du debat',$to($own->getEmail(),'Votre débat est publié'));
// argument d'un tiers
$c->loginUser($pro);$R('GET','/debats/'.$d->getSlug());$h=$c->getResponse()->getContent();
preg_match('/<form[^>]*>.*?<\/form>/s',$h,$fm);preg_match('/name="(argument\[_token\]|[a-z_]+\[_token\])" value="([^"]+)"/',$h,$tk);preg_match('/name="([a-z_]+)\[stance\]"/',$h,$sn);
$form=$sn[1]??'argument';$post=[$form=>['stance'=>'pour','content'=>'Argument documenté sur la question posée, avec des faits.','sourceUrl'=>'https://www.insee.fr/fr/statistiques/1','charter'=>'1','_token'=>$tk[2]??'']];
$R('POST','/debats/'.$d->getSlug().'/arguments',$post);
$a=$em->getRepository(Argument::class)->findOneBy(['debat'=>$d]);t('argument publie',(bool)$a);
t('auteur du debat prevenu par mail',$to($own->getEmail(),'Nouvel argument'));
$n=count($mails());
// second argument du meme auteur: pas de second mail (limite 6h)
$R('POST','/debats/'.$d->getSlug().'/arguments',$post);t('pas de second mail dans les 6 h',count($mails())===$n);
// reponse d'un autre: l'auteur de l'argument est prevenu
if($a){$c->loginUser($con);$R('GET','/debats/'.$d->getSlug().'?repondre='.$a->getId());$h=$c->getResponse()->getContent();preg_match('/name="[a-z_]+\[_token\]" value="([^"]+)"/',$h,$tk);preg_match('/name="([a-z_]+)\[parent\]"/',$h,$pn);
 $p2=[$form=>['stance'=>'contre','content'=>'Réponse argumentée avec une nuance factuelle.','reformulation'=>'Vous dites que le chiffre est documenté, ce que je comprends.','sourceUrl'=>'','charter'=>'1','_token'=>$tk[1]??'']];
 $R('POST','/debats/'.$d->getSlug().'/arguments?repondre='.$a->getId(),$p2);
 t('auteur de l argument prevenu d une reponse',$to($pro->getEmail(),'Réponse à votre argument'));}
// signalement
$c->loginUser($con);if($a){$R('GET','/signaler/argument/'.$a->getId());preg_match('/name="[a-z_]+\[_token\]" value="([^"]+)"/',$c->getResponse()->getContent(),$tk);$R('GET','/signaler/argument/'.$a->getId());
 $h=$c->getResponse()->getContent();preg_match('/name="([a-z_]+)\[reason\]"/',$h,$rn);}
// tribune
$j=$mk(['ROLE_USER','ROLE_JOURNALIST'],'jou');$c->loginUser($j);$R('GET','/journalistes/ecrire');preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$tk);
$body=str_repeat("Le texte documente un fait vérifiable et cite ses sources publiques. ",8);
$R('POST','/journalistes/ecrire',['_token'=>$tk[1],'title'=>'Tribune de test notifications','lead'=>'Un point sourcé sur un sujet de test pour les notifications.','body'=>$body,'sources'=>'https://www.education.gouv.fr/rapport']);
t('auteur de tribune: accuse de reception',$to($j->getEmail(),'Votre tribune est bien reçue'));
$tr=$em->getRepository(Tribune::class)->findOneBy(['author'=>$j]);
$c->loginUser($ad);$R('GET','/admin/moderation');$h=$c->getResponse()->getContent();preg_match('~tribune/'.$tr->getId().'/approve"[^>]*><input type="hidden" name="_token" value="([^"]+)"~',$h,$mt);
$R('POST','/admin/moderation/tribune/'.$tr->getId().'/approve',['_token'=>$mt[1]??'']);
t('tribune publiee: mail a l auteur',$to($j->getEmail(),'Votre tribune est publiée'));
t('admin prevenu d une tribune (MAIL_FROM)',count(array_filter($mails(),fn($x)=>str_contains($x[1],'[Admin] Nouvelle tribune')))>=1);
// statut clos par admin dans l ecran de gestion
$R('GET',$u);preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$R('POST',$u,['_token'=>$m[1],'title'=>$d->getTitle(),'theme'=>$d->getTheme(),'summary'=>$d->getSummary(),'facts'=>$d->getFacts(),'authority'=>'','status'=>'closed','closesAt'=>'','outcome'=>'']);
t('auteur prevenu de la cloture',$to($own->getEmail(),'Votre débat est clos'));
echo "$ok ok / $ko ko\n";
if(getenv('DEBUG')){print_r($mails());echo $c->getResponse()->getStatusCode(),"\n";}
