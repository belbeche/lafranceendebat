<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Event,Revendication,Notification,DirectMessage,Discussion};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
foreach(['DirectMessage','Notification'] as $e){$em->createQuery("DELETE App\\Entity\\$e x")->execute();}
function mk($em,$roles){$u=new User();$u->setEmail('u'.uniqid().'@orga-test.fr')->setPassword('x')->setRoles($roles);foreach(['setFirstName','setLastName'] as $m)if(method_exists($u,$m))$u->$m('T'.uniqid());$em->persist($u);$em->flush();return $u;}
$cit=mk($em,['ROLE_USER']);$elu=mk($em,['ROLE_USER','ROLE_ELU']);$cit2=mk($em,['ROLE_USER']);
// pages publiques
foreach(['/','/contribuables','/agenda','/missions'] as $u){$c->request('GET',$u);t("$u 200",$c->getResponse()->getStatusCode()==200);}
$c->request('GET','/');$h=$c->getResponse()->getContent();t('hero natif sans jpg',str_contains($h,'hx-native')&&!str_contains($h,'hx-banner'));
// anonyme redirige
$c->request('GET','/messages');t('messages anonyme -> login',$c->getResponse()->isRedirect());
// citoyen ecrit a l'elu
$c->loginUser($cit);
$c->request('GET','/messages/'.$elu->getId());t('thread ouvert',$c->getResponse()->getStatusCode()==200);
$h=$c->getResponse()->getContent();preg_match('/name="_token" value="([^"]+)"/',$h,$m);
$c->request('POST','/messages/'.$elu->getId(),['_token'=>$m[1],'body'=>'Bonjour, ma demande de contribuable sur le budget de ma commune.']);
t('message envoye',$em->getRepository(DirectMessage::class)->count([])==1);
t('notification creee',$em->getRepository(Notification::class)->count(['user'=>$elu])==1);
$c->request('POST','/messages/'.$elu->getId(),['_token'=>$m[1],'body'=>'ecrivez moi sur jean@mail.fr']);
t('fuite e-mail refusee',$em->getRepository(DirectMessage::class)->count([])==1);
// citoyen -> citoyen sans droit
$c->request('POST','/messages/'.$cit2->getId(),['_token'=>$m[1],'body'=>'Salut']);t('citoyen vers citoyen refuse',$c->getResponse()->getStatusCode()==403);
// elu : live + flux + lecture
$c->loginUser($elu);
$c->request('GET','/api/live');$j=json_decode($c->getResponse()->getContent(),true);t('live compte message',($j['messages']??0)==1&&($j['notifications']??0)>=1);
t('live latest',!empty($j['latest'][0]['text']));
$c->request('GET','/messages');t('inbox liste',str_contains($c->getResponse()->getContent(),'Bonjour, ma demande'));
$c->request('GET','/messages/'.$cit->getId());$h=$c->getResponse()->getContent();t('thread elu affiche',str_contains($h,'ma demande de contribuable'));
$c->request('GET','/api/live');$j=json_decode($c->getResponse()->getContent(),true);t('messages lus',($j['messages']??1)==0);
preg_match('/name="_token" value="([^"]+)"/',$h,$m);
$c->request('POST','/messages/'.$cit->getId(),['_token'=>$m[1],'body'=>'Merci, je prends votre demande en compte.']);
$c->loginUser($cit);
$c->request('GET','/messages/'.$elu->getId().'/flux?apres=0');$j=json_decode($c->getResponse()->getContent(),true);t('flux temps reel',count($j['messages']??[])==1&&str_contains($j['messages'][0]['body'],'prends votre demande'));
$c->request('GET','/notifications');t('page notifications',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Nouveau message de'));
// mes demandes
$c->request('GET','/mes-demandes');t('mes demandes',$c->getResponse()->getStatusCode()==200);
// agenda
$ev=(new Event());$ev->setName('Debat test')->setDate(new DateTime('+5 days'))->setDescription('Un debat')->setStatus('publie');if(method_exists($ev,'setKind'))$ev->setKind('debat');$em->persist($ev);$em->flush();
$c->request('GET','/agenda');$h=$c->getResponse()->getContent();t('agenda affiche rencontre',str_contains($h,'Debat test')&&str_contains($h,'Ouvrir la conversation'));
preg_match('/action="\/agenda\/'.$ev->getId().'\/echanger".*?name="_token" value="([^"]+)"/s',$h,$m);
$c->request('POST','/agenda/'.$ev->getId().'/echanger',['_token'=>$m[1]??'x']);t('conversation creee',$c->getResponse()->isRedirect()&&str_contains($c->getResponse()->headers->get('Location'),'/communaute/'));
t('fil lie',$em->getRepository(Discussion::class)->count(['event'=>$ev])==1);
$c->request('GET','/agenda/'.$ev->getId().'.ics');t('ics',str_contains($c->getResponse()->getContent(),'BEGIN:VEVENT')&&str_contains((string)$c->getResponse()->headers->get('Content-Type'),'calendar'));
$c->request('GET','/agenda');t('lien conversation',str_contains($c->getResponse()->getContent(),'Rejoindre la conversation'));
echo "$ok ok / $ko ko\n";
// roles
$c->getCookieJar()->clear();
$c->request('GET','/propositions');$h=$c->getResponse()->getContent();t('visiteur: panneau lecture',str_contains($h,'Vous visitez en lecture'));
$c->loginUser($cit);$c->request('GET','/propositions');$h=$c->getResponse()->getContent();file_put_contents('/tmp/prop.html',$h);echo substr_count($h,'Écrire une proposition'),substr_count($h,'Demander un droit d'),substr_count($h,'Donner un avis motivé'),"\n";t('citoyen: panneau citoyen',str_contains($h,'/propositions/nouvelle')&&str_contains($h,'/candidater')&&!str_contains($h,'avis-acteur'));
$c->loginUser($elu);$c->request('GET','/propositions');$h=$c->getResponse()->getContent();t('elu: avis et messagerie',str_contains($h,'Donner un avis motivé')&&str_contains($h,'Mon espace élu'));
$c->request('GET','/messages/'.$cit2->getId());$h=$c->getResponse()->getContent();t('acteur peut ecrire a un citoyen',str_contains($h,'Votre message'));
$c->loginUser($cit);$c->request('GET','/messages/'.$cit2->getId());$h=$c->getResponse()->getContent();t('citoyen ne peut pas ecrire a un citoyen',!str_contains($h,'name="body"'));
echo "$ok ok / $ko ko\n";
