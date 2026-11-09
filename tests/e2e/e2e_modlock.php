<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,MissionPost};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$mk=function($r) use($em){$u=new User();$u->setEmail('m'.uniqid().'@t.fr');$u->setPassword('x');$u->setFirstName('Mod');$u->setLastName(uniqid());$u->setRoles($r);$em->persist($u);$em->flush();return $u;};
$m1=$mk(['ROLE_MODERATOR']);$m2=$mk(['ROLE_MODERATOR']);$au=$mk(['ROLE_USER']);
$p=new MissionPost($au,'offre','Relire une traduction','Je propose une heure par semaine pour relire des traductions.',null,'benevole');$em->persist($p);$em->flush();$id=$p->getId();
$tok=function() use($c){$c->request('GET','/admin/moderation');preg_match_all('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);return $m[1];};
$c->loginUser($m1);$c->request('GET','/admin/moderation');$h=$c->getResponse()->getContent();
t('bouton prendre en charge',str_contains($h,'Prendre en charge'));
t('page moderation 200 + equipe',$c->getResponse()->getStatusCode()==200&&str_contains($h,'Équipe, aujourd'));
t('motifs prets',str_contains($h,'Source absente ou non vérifiable'));
$tk=function($path) use($c){$c->request('GET','/admin/moderation');$h=$c->getResponse()->getContent();preg_match('~action="'.preg_quote($path,'~').'"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"~',$h,$m);return $m[1]??'';};
$c->request('POST','/admin/moderation/prendre/annonce/'.$id,['_token'=>$tk('/admin/moderation/prendre/annonce/'.$id)]);
t('prise en charge ok',$c->getResponse()->isRedirect());
$c->loginUser($m2);
$c->request('POST','/admin/moderation/annonce-mission/'.$id.'/approve',['_token'=>$tk('/admin/moderation/annonce-mission/'.$id.'/approve')]);
echo $c->getResponse()->getStatusCode(),"\n";$c->followRedirect();t('collegue bloque',str_contains($c->getResponse()->getContent(),'a pris ce contenu en charge'));
$em->clear();t('annonce toujours en attente',$em->getRepository(MissionPost::class)->find($id)->getStatus()===MissionPost::PENDING);
$c->loginUser($m1);
$c->request('POST','/admin/moderation/annonce-mission/'.$id.'/approve',['_token'=>$tk('/admin/moderation/annonce-mission/'.$id.'/approve')]);
$em->clear();t('detenteur peut publier',$em->getRepository(MissionPost::class)->find($id)->getStatus()!==MissionPost::PENDING);
$c->request('GET','/admin/moderation');t("charge: traite",preg_match("~\\d+ traité~",$c->getResponse()->getContent())===1);
echo "\n$ok OK / $ko KO\n";
