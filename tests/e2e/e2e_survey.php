<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{Survey,SurveyResponse,User,NewsletterSubscriber};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k=new App\Kernel('test',true);$k->boot();
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$c=$k->getContainer();$em=$c->get('doctrine')->getManager();
$mk=function($r){global $em;$u=new User();$u->setEmail('sv'.uniqid().'@t.fr')->setPassword('x')->setFirstName('S')->setLastName('V')->setRoles($r);$em->persist($u);$em->flush();return $u;};
$em->createQuery('DELETE FROM App\Entity\Survey')->execute();
$adm=$mk(['ROLE_ADMIN']);$mod=$mk(['ROLE_USER','ROLE_MODERATOR']);$cit=$mk(['ROLE_USER']);
// parsing
[$q,$e]=Survey::parse("choix* | Quelle priorité pour 2027 ? | Santé ; École ; Justice\noui-non | Voteriez-vous en ligne ?\nechelle | Confiance dans les institutions\ntexte | Un dernier mot ?");
t('parse: 4 questions sans erreur',count($q)===4&&!$e&&$q[0]['required']&&$q[1]['options']===['Oui','Non']);
[$q2,$e2]=Survey::parse("inconnu | Question | a;b\nchoix | Q | seule");
t('parse: erreurs lisibles',count($e2)===2&&str_contains($e2[0],'Ligne 1'));
// accès admin
$bm=new KernelBrowser($k);$bm->loginUser($cit);$bm->request('GET','/admin/enquetes');t('membre refusé',$bm->getResponse()->getStatusCode()==403);
$ba=new KernelBrowser($k);$ba->loginUser($mod);
$cr=$ba->request('GET','/admin/enquetes/nouvelle');t('formulaire admin 200',$ba->getResponse()->getStatusCode()==200);
$tok=$cr->filter('main input[name=_token]')->first()->attr('value');
$ba->request('POST','/admin/enquetes/nouvelle',['_token'=>$tok,'title'=>'Priorités citoyennes 2027','intro'=>'Aidez-nous à fixer les priorités du débat public pour la prochaine année.','questions'=>"choix* | Quelle priorité ? | Santé ; École ; Justice\nechelle | Confiance dans les institutions\ntexte | Un dernier mot ?",'public'=>1]);
$s=$em->getRepository(Survey::class)->findOneBy(['title'=>'Priorités citoyennes 2027'],['id'=>'DESC']);
t('brouillon créé + redirection vers l\'aperçu',$s&&$s->isDraft()&&$ba->getResponse()->isRedirect('/admin/enquetes/'.$s->getId().'/apercu'));
// refus d'un contenu interdit / question invalide
$ba->request('POST','/admin/enquetes/nouvelle',['_token'=>$tok,'title'=>'Titre ok ok','intro'=>'Une présentation suffisamment longue pour passer.','questions'=>'choix | Q sans options']);
t('question invalide: rien créé',$ba->getResponse()->getStatusCode()==200&&str_contains($ba->getResponse()->getContent(),'Ligne 1'));
// publication impossible sans aperçu
$cr=$ba->request('GET','/admin/enquetes');$tk=$cr->filter('main input[name=_token]')->count()?null:null;
$csrf=function($id)use($k,$ba){$cr=$ba->request('GET','/admin/enquetes/'.$id.'/apercu');return $cr->filter('form[action*=publier] input[name=_token]')->attr('value');};
$s=$em->find(Survey::class,$s->getId());t('pas encore prévisualisée',!$s->isPreviewed());
// obtenir le jeton CSRF sans ouvrir l'aperçu : via la liste
$cr=$ba->request('GET','/admin/enquetes');$tokS=$cr->filter('main input[name=_token]')->reduce(fn($n)=>true)->first()->attr('value');
$ba->request('POST','/admin/enquetes/'.$s->getId().'/publier',['_token'=>$tokS]);
$em->clear();$s=$em->find(Survey::class,$s->getId());t('publication refusée sans aperçu',$s->isDraft());
// aperçu puis publication avec envoi
$ba->request('GET','/admin/enquetes/'.$s->getId().'/apercu');$h=$ba->getResponse()->getContent();
t('aperçu affiche les questions',$ba->getResponse()->getStatusCode()==200&&str_contains($h,'Quelle priorité')&&str_contains($h,'Aperçu'));
$em->clear();$s=$em->find(Survey::class,$s->getId());t('aperçu enregistré',$s->isPreviewed());
$ba->request('POST','/admin/enquetes/'.$s->getId().'/publier',['_token'=>$tokS,'envoyer'=>1]);
$em->clear();$s=$em->find(Survey::class,$s->getId());t('publiée',$s->isOpen()&&$s->getSendStatus()==='sending');
// modification d'une enquête publiée refusée
$ba->request('GET','/admin/enquetes/'.$s->getId().'/modifier');t('enquête publiée non modifiable',$ba->getResponse()->isRedirect('/admin/enquetes'));
// public : réponse anonyme
$bp=new KernelBrowser($k);$bp->request('GET','/enquetes');t('liste publique',str_contains($bp->getResponse()->getContent(),'Priorités citoyennes 2027'));
$cr=$bp->request('GET','/enquetes/'.$s->getId());t('formulaire public 200',$bp->getResponse()->getStatusCode()==200);
$ptok=$cr->filter('main input[name=_token]')->attr('value');
$bp->request('POST','/enquetes/'.$s->getId(),['_token'=>$ptok,'q'=>[1=>'3']]);
t('réponse obligatoire manquante: refusée (422)',$bp->getResponse()->getStatusCode()==422&&str_contains($bp->getResponse()->getContent(),'obligatoire'));
$bp->request('POST','/enquetes/'.$s->getId(),['_token'=>$ptok,'q'=>['Santé','4','Merci à vous']]);
t('réponse enregistrée (redirection merci)',$bp->getResponse()->isRedirect('/enquetes/'.$s->getId().'/merci'));
$n=fn()=>(int)$em->createQuery('SELECT COUNT(r.id) FROM App\Entity\SurveyResponse r WHERE r.survey=:s')->setParameter('s',$s)->getSingleScalarResult();
t('1 réponse',$n()===1);
$bp->request('POST','/enquetes/'.$s->getId(),['_token'=>$ptok,'q'=>['Justice','1','Autre']]);
t('même navigateur: pas de 2e réponse',$n()===1);
$bp->request('POST','/enquetes/'.$s->getId(),['_token'=>$ptok,'website'=>'spam','q'=>['Justice','1','x']]);
// valeur hors liste ignorée -> obligatoire échoue
$b2=new KernelBrowser($k);$cr=$b2->request('GET','/enquetes/'.$s->getId());$t2=$cr->filter('main input[name=_token]')->attr('value');
$b2->request('POST','/enquetes/'.$s->getId(),['_token'=>$t2,'q'=>['Option piratée','9','x']]);
t('valeur hors liste rejetée',$b2->getResponse()->getStatusCode()==422&&$n()===1);
$b2->request('POST','/enquetes/'.$s->getId(),['_token'=>$t2,'q'=>['École','2','Ce connard ment']]);
t('insulte refusée',$b2->getResponse()->getStatusCode()==422&&$n()===1);
$b2->request('POST','/enquetes/'.$s->getId(),['_token'=>'faux','q'=>['École','2','ok']]);
t('CSRF invalide refusé',$b2->getResponse()->getStatusCode()==422&&$n()===1);
// membre connecté : une réponse
$b3=new KernelBrowser($k);$b3->loginUser($cit);$cr=$b3->request('GET','/enquetes/'.$s->getId());$t3=$cr->filter('main input[name=_token]')->attr('value');
$b3->request('POST','/enquetes/'.$s->getId(),['_token'=>$t3,'q'=>['École','5','']]);$b3->request('POST','/enquetes/'.$s->getId(),['_token'=>$t3,'q'=>['Santé','5','']]);
t('membre: une seule réponse',$n()===2);
// résultats : masqués < 5
$bp->request('GET','/enquetes/'.$s->getId().'/resultats');t('résultats masqués sous 5 réponses',str_contains($bp->getResponse()->getContent(),'protéger l\'anonymat'));
for($i=0;$i<4;$i++){$bx=new KernelBrowser($k);$cr=$bx->request('GET','/enquetes/'.$s->getId());$bx->request('POST','/enquetes/'.$s->getId(),['_token'=>$cr->filter('main input[name=_token]')->attr('value'),'q'=>['Santé',(string)(1+$i),'avis '.$i]]);}
$bp->request('GET','/enquetes/'.$s->getId().'/resultats');$h=$bp->getResponse()->getContent();
t('résultats publics dès 5 réponses ('.$n().')',$n()>=5&&str_contains($h,'Santé')&&str_contains($h,'moyenne')&&!str_contains($h,'avis 0'));
// admin : résultats + CSV
$ba->request('GET','/admin/enquetes/'.$s->getId().'/resultats');t('résultats admin avec réponses libres',str_contains($ba->getResponse()->getContent(),'avis 0'));
$ba->request('GET','/admin/enquetes/'.$s->getId().'/export.csv');$csv=$ba->getInternalResponse()->getContent();t('export CSV',str_contains($csv,'Quelle priorité')&&substr_count($csv,"\n")>=6);
// envoi par lots aux abonnés confirmés, sans doublon
$em->createQuery("DELETE FROM App\Entity\NewsletterSubscriber s WHERE s.email LIKE '%@sv.test'")->execute();
for($i=0;$i<3;$i++){$sub=(new NewsletterSubscriber())->setEmail("a$i@sv.test")->setSource('site');$sub->setIsActive(true);$sub->setConfirmedAt(new DateTimeImmutable());$em->persist($sub);}
$em->flush();
$ru=(new ReflectionClass(App\Entity\NewsletterSubscriber::class));
$run=function()use($k){$tmp=sys_get_temp_dir().'/lfed_sv_'.uniqid().'.php';file_put_contents($tmp,"<?php require '/home/claude/app/vendor/autoload.php';(new Symfony\\Component\\Dotenv\\Dotenv())->bootEnv('/home/claude/app/.env');\$k=new App\\Kernel('test',true);\$k->boot();\$a=new Symfony\\Bundle\\FrameworkBundle\\Console\\Application(\$k);\$a->setAutoExit(false);\$a->run(new Symfony\\Component\\Console\\Input\\ArrayInput(['command'=>'app:enquete:envoyer','--max'=>'500']));");$o=shell_exec('APP_ENV=test MAIL_FROM=no-reply@lafranceendebat.fr php '.escapeshellarg($tmp).' 2>&1');@unlink($tmp);return (string)$o;};
$o1=$run();$em->clear();$s=$em->find(Survey::class,$s->getId());
echo "SEND: ".trim($o1)." status=".$s->getSendStatus()."\n";t('envoi: terminé ou readiness explicite',$s->getSendStatus()==='done'||str_contains($o1,'adresse'));
if($s->getSendStatus()==='done'){$sent=$s->getSentCount();$run();$em->clear();$s=$em->find(Survey::class,$s->getId());t("relance: aucun doublon ($sent)",$s->getSentCount()===$sent);}
// clôture
$ba->request('POST','/admin/enquetes/'.$s->getId().'/cloturer',['_token'=>$tokS]);$em->clear();$s=$em->find(Survey::class,$s->getId());
t('clôturée',$s->getStatus()==='closed');
$bq=new KernelBrowser($k);$bq->request('GET','/enquetes/'.$s->getId());t('clôturée: pas de formulaire',str_contains($bq->getResponse()->getContent(),'clôturée'));
$bd=new KernelBrowser($k);$bd->request('GET','/enquetes/999999');t('inconnue: 404',$bd->getResponse()->getStatusCode()==404);
// suppression: admin seulement
$ba->request('POST','/admin/enquetes/'.$s->getId().'/supprimer',['_token'=>$tokS]);t('modérateur ne peut pas supprimer',$ba->getResponse()->getStatusCode()==403);
echo "\n$ok OK / $ko KO\n";
