<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Proposition,Revendication,Discussion,ActorEndorsement,DirectMessage,Notification};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$em=$c->getContainer()->get('doctrine')->getManager();
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
function mk($em,$roles,$tag){$u=new User();$u->setEmail($tag.uniqid().'@orga-test.fr')->setPassword('x')->setRoles($roles);$u->setFirstName(ucfirst($tag));$u->setLastName('Test');$em->persist($u);$em->flush();return $u;}
function tok($c,$u,$sel='/name="_token" value="([^"]+)"/'){$c->request('GET',$u);preg_match($sel,$c->getResponse()->getContent(),$m);return $m[1]??'';}
$U=substr(uniqid(),-5);$cit=mk($em,['ROLE_USER'],'cit');$mod=mk($em,['ROLE_USER','ROLE_MODERATOR'],'mod');$elu=mk($em,['ROLE_USER','ROLE_ELU'],'elu');$cit2=mk($em,['ROLE_USER'],'cit2');
echo "--- Contribuable\n";
$c->loginUser($cit);
$tk=tok($c,'/revendications/proposer');
$c->request('POST','/revendications/proposer',['_token'=>$tk,'title'=>'Délais de remboursement des frais de santé '.$U,'summary'=>str_repeat('Les remboursements prennent plusieurs mois dans ma commune, avec des conséquences pour les familles modestes. ',2),'groupLabel'=>'Contribuables','theme'=>Proposition::THEMES[0],'sourceUrl'=>'https://www.ameli.fr/assure','sourceLabel'=>'Ameli','authority'=>'CPAM','locale'=>'fr']);
$em->clear();$cl=$em->getRepository(Revendication::class)->findOneBy(['title'=>'Délais de remboursement des frais de santé '.$U]);
t('demande creee non publiee',$cl&&!$cl->isPublished()&&$cl->getSubmittedBy()->getId()===$cit->getId());
$c->request('GET','/revendications');t('non visible publiquement',!str_contains($c->getResponse()->getContent(),'Délais de remboursement des frais de santé '.$U));
$c->request('GET','/mes-demandes');t('visible dans mes demandes',str_contains($c->getResponse()->getContent(),'Délais de remboursement des frais de santé '.$U));
echo "--- Moderateur\n";
$c->loginUser($em->getRepository(User::class)->find($mod->getId()));
$c->request('GET','/admin/moderation');t('moderateur accede moderation '.$c->getResponse()->getStatusCode(),$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Délais de remboursement des frais de santé '.$U));
$c->request('GET','/admin/users/');t('moderateur refuse admin/users',in_array($c->getResponse()->getStatusCode(),[302,403]));
$tk=tok($c,'/admin/moderation','/moderateclaim'.$cl->getId().'|name="_token" value="([^"]+)"/');
preg_match('/action="[^"]*revendication\/'.$cl->getId().'\/reject"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);$tk=$m[1]??'';
$c->request('POST','/admin/moderation/revendication/'.$cl->getId().'/reject',['_token'=>$tk,'note'=>'']);$em->clear();t('refus sans motif bloque',!$em->getRepository(Revendication::class)->find($cl->getId())->getModerationNote());
$c->request('POST','/admin/moderation/revendication/'.$cl->getId().'/reject',['_token'=>$tk,'note'=>'Source trop générale, citez la décision précise.']);$em->clear();
$r=$em->getRepository(Revendication::class)->find($cl->getId());t('refus motive enregistre',!$r->isPublished()&&str_contains((string)$r->getModerationNote(),'Source trop'));
$n=$em->getRepository(Notification::class)->count(['user'=>$cit]);t('auteur notifie du refus',$n>=1);
$c->loginUser($em->getRepository(User::class)->find($cit->getId()));$c->request('GET','/mes-demandes');t('motif visible par le contribuable',str_contains($c->getResponse()->getContent(),'Source trop'));
// nouvelle demande publiee
$c->request('POST','/revendications/proposer',['_token'=>tok($c,'/revendications/proposer'),'title'=>'Accès aux urgences en zone rurale '.$U,'summary'=>str_repeat('Les urgences les plus proches sont à plus de quarante minutes pour de nombreuses communes rurales. ',2),'groupLabel'=>'Contribuables','theme'=>Proposition::THEMES[0],'sourceUrl'=>'https://www.cour-des-comptes.fr/','sourceLabel'=>'','authority'=>'','locale'=>'fr']);
$em->clear();$cl2=$em->getRepository(Revendication::class)->findOneBy(['title'=>'Accès aux urgences en zone rurale '.$U]);
$c->loginUser($em->getRepository(User::class)->find($mod->getId()));$c->request('GET','/admin/moderation');
preg_match('/revendication\/'.$cl2->getId().'\/approve"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST','/admin/moderation/revendication/'.$cl2->getId().'/approve',['_token'=>$m[1]??'']);$em->clear();
t('moderateur publie la demande',$em->getRepository(Revendication::class)->find($cl2->getId())->isPublished());
$c->request('GET','/revendications');t('demande publiee visible',str_contains($c->getResponse()->getContent(),'Accès aux urgences en zone rurale '.$U));
echo "--- Moderateur: proposition\n";
$cit=$em->getRepository(User::class)->find($cit->getId());
$p=(new Proposition())->setTitle('Guichet unique pour les aides locales')->setDescription(str_repeat('Regrouper les aides en un seul guichet réduirait les démarches. ',4))->setTheme(Proposition::THEMES[0])->setAuthor($cit);$em->persist($p);$em->flush();
$c->loginUser($em->getRepository(User::class)->find($mod->getId()));$c->request('GET','/admin/moderation');
t('proposition en attente listee',str_contains($c->getResponse()->getContent(),'Guichet unique'));
preg_match('/proposition\/'.$p->getId().'\/approve"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"/',$c->getResponse()->getContent(),$m);
$c->request('POST','/admin/moderation/proposition/'.$p->getId().'/approve',['_token'=>$m[1]??'']);$em->clear();
t('proposition publiee par moderateur',$em->getRepository(Proposition::class)->find($p->getId())->getStatus()==='Validée');
echo "--- Acteur (elu)\n";
$c->loginUser($em->getRepository(User::class)->find($elu->getId()));
$c->request('GET','/propositions/'.$p->getId());t('fiche proposition 200',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/messages/'.$cit2->getId());t('acteur peut ecrire a un citoyen',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/agenda/proposer');t('acteur propose une rencontre',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/espace-acteur/candidatures');t('acteur: candidatures recues',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/classement');t('classement lisible',$c->getResponse()->getStatusCode()==200);
$c->loginUser($em->getRepository(User::class)->find($cit->getId()));$c->request('POST','/messages/'.$cit2->getId(),['body'=>'Bonjour','_token'=>'x']);t('citoyen vers citoyen refuse (POST)',$c->getResponse()->getStatusCode()==403);
$c->request('GET','/espace-acteur/candidatures');t('citoyen sans acces candidatures recues',$c->getResponse()->getStatusCode()==403);
echo "$ok ok / $ko ko\n";
echo "$ok ok / $ko ko\n";
