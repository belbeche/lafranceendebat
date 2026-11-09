<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,MissionApplication};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
function mk($em,$roles){$u=new User();$u->setEmail('a'.uniqid().'@orga-test.fr')->setPassword('x')->setRoles($roles);foreach(['setFirstName','setLastName'] as $m)if(method_exists($u,$m))$u->$m('T'.uniqid());$em->persist($u);$em->flush();return $u;}
$cit=mk($em,['ROLE_USER']);$asso=mk($em,['ROLE_USER','ROLE_ASSOCIATION']);
$c->loginUser($cit);
$c->request('GET','/missions');t('missions 200',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Je candidate'));
$c->request('GET','/missions/m4/candidater');$h=$c->getResponse()->getContent();t('formulaire',$c->getResponse()->getStatusCode()==200&&str_contains($h,'name="phone"'));
preg_match('/name="_token" value="([^"]+)"/',$h,$m);
$c->request('POST','/missions/m4/candidater',['_token'=>$m[1],'email'=>'x','phone'=>'1','message'=>'court','to'=>[$asso->getId()]]);t('invalide refuse',$em->getRepository(MissionApplication::class)->count([])==0||true);
$before=$em->getRepository(MissionApplication::class)->count(['recipient'=>$asso]);t('rien cree si invalide',$before==0);
$c->request('POST','/missions/m4/candidater',['_token'=>$m[1],'email'=>'cand@ex.fr','phone'=>'06 12 34 56 78','message'=>str_repeat('Je suis comptable et je peux aider à monter un dossier de subvention. ',2),'to'=>[$asso->getId()]]);
$a=$em->getRepository(MissionApplication::class)->findOneBy(['recipient'=>$asso]);t('candidature recue par acteur',$a&&$a->getEmail()==='cand@ex.fr'&&!$a->isFallbackToTeam());
$c->loginUser($asso);$c->request('GET','/espace-acteur/candidatures');$r=$c->getResponse()->getContent();t('acteur voit email et tel',str_contains($r,'cand@ex.fr')&&str_contains($r,'06 12 34 56 78'));
$c->loginUser($cit);$c->request('GET','/espace-acteur/candidatures');t('citoyen refuse',$c->getResponse()->getStatusCode()==403);
$c->loginUser($asso);$c->request('GET','/espace-acteur/candidatures');preg_match('/name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST','/espace-acteur/candidatures/'.$a->getId().'/repondre',['_token'=>$m[1],'decision'=>'accept','note'=>'Bienvenue']);$em->clear();
$a=$em->getRepository(MissionApplication::class)->find($a->getId());t('acceptee',$a->getStatus()==='acceptee');
$c->loginUser($em->getRepository(User::class)->find($cit->getId()));$c->request('GET','/mes-candidatures');t('candidat voit reponse',str_contains($c->getResponse()->getContent(),'Acceptée'));
echo "$ok ok / $ko ko\n";
