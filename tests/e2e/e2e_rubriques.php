<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Dossier,Inspiration};
$k=new App\Kernel('test',true);$k->boot();$c=new Symfony\Bundle\FrameworkBundle\KernelBrowser($k);
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$k->getContainer()->get('doctrine')->getManager();
$mk=function($r) use($em){$u=new User();$u->setEmail('r'.uniqid().'@t.fr');$u->setPassword('x');$u->setFirstName('A');$u->setLastName(uniqid());$u->setRoles($r);$em->persist($u);$em->flush();return $u;};
$adm=$mk(['ROLE_ADMIN']);$cit=$mk(['ROLE_USER']);
$d=(new Dossier())->setAuthor($cit)->setTitle('Dossier test rubriques')->setSlug('dt-'.uniqid())->setIntro('Intro')->setBody(str_repeat('Texte ',10))->setSources('https://www.insee.fr');$em->persist($d);$em->flush();$id=$d->getId();
$c->loginUser($cit);$c->request('GET','/admin/rubriques');t('citoyen refuse',$c->getResponse()->getStatusCode()==403);
$c->loginUser($adm);$c->request('GET','/admin/rubriques');t('hub 200',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Dossiers de référence'));
$c->request('GET','/admin/rubriques/dossiers?statut=pending&q=rubriques');$h=$c->getResponse()->getContent();t('liste filtre',str_contains($h,'Dossier test rubriques'));
$tk=function() use($c,$id){$c->request('GET','/admin/rubriques/dossiers');preg_match('~/dossiers/'.$id.'/publier"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"~',$c->getResponse()->getContent(),$m);return $m[1]??'';};
$tok=$tk();t('jeton',$tok!=='');
$c->request('POST',"/admin/rubriques/dossiers/$id/publier",['_token'=>'faux']);$em->clear();t('csrf refuse',$em->getRepository(Dossier::class)->find($id)->getStatus()==='pending');
$c->request('POST',"/admin/rubriques/dossiers/$id/publier",['_token'=>$tok]);$em->clear();t('publie',$em->getRepository(Dossier::class)->find($id)->isPublished());
$c->request('POST',"/admin/rubriques/dossiers/$id/retirer",['_token'=>$tok]);$em->clear();t('retire',$em->getRepository(Dossier::class)->find($id)->getStatus()==='pending');
$c->request('POST',"/admin/rubriques/dossiers/$id/refuser",['_token'=>$tok,'note'=>'']);$em->clear();t('refus sans motif bloque',$em->getRepository(Dossier::class)->find($id)->getStatus()==='pending');
$c->request('POST',"/admin/rubriques/dossiers/$id/refuser",['_token'=>$tok,'note'=>'Source absente']);$em->clear();t('refus motive',$em->getRepository(Dossier::class)->find($id)->getModerationNote()==='Source absente');
$c->request('GET',"/admin/rubriques/dossiers/$id/modifier");$h=$c->getResponse()->getContent();preg_match('/name="_token" value="([^"]+)"/',$h,$m);
$c->request('POST',"/admin/rubriques/dossiers/$id/modifier",['_token'=>$m[1],'title'=>'Titre corrigé','intro'=>'Intro','body'=>str_repeat('Corps ',12),'theme'=>'','sources'=>'http://non-https']);t('source http refusee',str_contains($c->getResponse()->getContent(),'adresse https'));
$c->request('POST',"/admin/rubriques/dossiers/$id/modifier",['_token'=>$m[1],'title'=>'Titre corrigé','intro'=>'Intro','body'=>str_repeat('Corps ',12),'theme'=>'','sources'=>'https://www.insee.fr']);$em->clear();t('corrige',$em->getRepository(Dossier::class)->find($id)->getTitle()==='Titre corrigé');
$c->request('POST',"/admin/rubriques/dossiers/$id/supprimer",['_token'=>$tok]);$em->clear();t('supprime',$em->getRepository(Dossier::class)->find($id)===null);
$c->request('GET','/admin/rubriques/annonces');t('annonces 200',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/admin/rubriques/tribunes');t('tribunes 200',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/admin/rubriques/contributeurs');t('contributeurs 200',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/admin/rubriques');$h=$c->getResponse()->getContent();t('vue ensemble',str_contains($h,'Vue d\'ensemble')&&str_contains($h,'Propositions')&&str_contains($h,'Signalements'));
$c->request('GET','/admin/rubriques/dossiers');$h=$c->getResponse()->getContent();t('dossiers integres listes',str_contains($h,'Intégrés à la plateforme')&&str_contains($h,'La Constitution et notre démarche'));
$c->request('GET','/admin/rubriques/contributeurs');preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
if($m){$c->request('POST','/admin/rubriques/contributeurs/charger',['_token'=>$m[1]]);}
$c->request('GET','/admin/rubriques/contributeurs');t('fiches chargees',str_contains($c->getResponse()->getContent(),'Marie Curie'));
$c->request('GET','/admin/rubriques');preg_match('~maintenance/cache"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"~',$c->getResponse()->getContent(),$m);
$c->request('POST','/admin/maintenance/cache',['_token'=>$m[1]??'x']);t('vider caches',$c->getResponse()->isRedirect());$c->followRedirect();t('message caches',str_contains($c->getResponse()->getContent(),'Vidés'));
echo "\n$ok OK / $ko KO\n";
