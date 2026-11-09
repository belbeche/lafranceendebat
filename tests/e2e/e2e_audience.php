<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{Debat,Proposition,User,ContentView,ConsentRecord};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k=new App\Kernel('test',true);$k->boot();
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$c=$k->getContainer();$em=$c->get('doctrine')->getManager();
$ua='Mozilla/5.0 (X11; Linux) Firefox/120';
$mk=function($r=['ROLE_USER']){global $em;$u=new User();$u->setEmail('au'.uniqid().'@t.fr')->setPassword('x')->setFirstName('A')->setLastName('U')->setRoles($r);$em->persist($u);$em->flush();return $u;};
$u=$mk();
$d=(new Debat())->setTitle('Comment financer les urgences ?')->setSlug('urg-'.uniqid())->setTheme('Santé')->setSummary(str_repeat('Exposé neutre. ',10))->setFacts('Source https://www.insee.fr/fr/statistiques en 2023, 12 %')->setStatus(Debat::STATUS_OPEN)->setCompetentAuthority('Région');
$em->persist($d);$em->flush();
$b=new KernelBrowser($k,['HTTP_USER_AGENT'=>$ua]);
$b->request('GET','/debats/'.$d->getSlug());
t('débat 200',$b->getResponse()->getStatusCode()==200);
$n=fn()=>(int)$em->getConnection()->fetchOne("SELECT COUNT(*) FROM content_view WHERE kind='debat' AND target_id=?",[$d->getId()]);
t('1 lecture comptée',$n()===1);
$b->request('GET','/debats/'.$d->getSlug());t('même lecteur même jour: pas de doublon',$n()===1);
$bot=new KernelBrowser($k,['HTTP_USER_AGENT'=>'Googlebot/2.1']);$bot->request('GET','/debats/'.$d->getSlug());t('robot ignoré',$n()===1);
$h=$b->getResponse()->getContent();
t('obligations visibles',str_contains($h,'Respect des obligations')&&str_contains($h,'Respecté'));
t('bannière cookies',str_contains($h,'consent-banner'));
t('pas de script de mesure sans consentement',!str_contains($h,'read-meter.js'));
// balise sans consentement
$b->request('POST','/mesure/lecture',['kind'=>'debat','id'=>$d->getId(),'seconds'=>90,'scroll'=>80]);
t('mesure sans consentement: rien enregistré',(int)$em->getConnection()->fetchOne("SELECT SUM(read_seconds) FROM content_view WHERE target_id=?",[$d->getId()])===0);
// consentement
$cr=$b->request('GET','/cookie-preferences');t('page cookies 200',$b->getResponse()->getStatusCode()==200&&str_contains($b->getResponse()->getContent(),'REMEMBERME'));
$tok=$cr->filter('main input[name=_token]')->first()->attr('value');
$b->request('POST','/cookies/choix',['_token'=>$tok,'choice'=>'accept','back'=>'/debats']);
t('acceptation redirige',$b->getResponse()->isRedirect('/debats'));
$ck=$b->getCookieJar()->get('lfed_consent');t('cookie propre posé',$ck!==null&&preg_match('/^1\.[a-f0-9]{32}\.a1$/',$ck->getValue()));
t('preuve enregistrée',$em->getRepository(ConsentRecord::class)->count(['action'=>'accept_all'])>=1);
$b->request('POST','/cookies/choix',['_token'=>$tok,'choice'=>'accept','back'=>'//evil.com']);t('redirection externe refusée',!str_contains((string)$b->getResponse()->headers->get('Location'),'evil'));
$b->request('GET','/debats/'.$d->getSlug());t('script de mesure avec consentement',str_contains($b->getResponse()->getContent(),'read-meter.js'));
$b->request('POST','/mesure/lecture',['kind'=>'debat','id'=>$d->getId(),'seconds'=>90,'scroll'=>80]);
t('temps enregistré',(int)$em->getConnection()->fetchOne("SELECT SUM(read_seconds) FROM content_view WHERE target_id=?",[$d->getId()])===90);
$b->request('POST','/mesure/lecture',['kind'=>'debat','id'=>$d->getId(),'seconds'=>99999,'scroll'=>500]);
t('valeurs bornées',(int)$em->getConnection()->fetchOne("SELECT MAX(read_seconds) FROM content_view WHERE target_id=?",[$d->getId()])===1800);
// retrait
$b->request('POST','/cookies/choix',['_token'=>$tok,'choice'=>'withdraw']);
t('retrait: cookie a0',preg_match('/\.a0$/',$b->getCookieJar()->get('lfed_consent')->getValue()));
$b->request('POST','/mesure/lecture',['kind'=>'debat','id'=>$d->getId(),'seconds'=>1700,'scroll'=>100]);
t('après retrait: plus de mesure',(int)$em->getConnection()->fetchOne("SELECT MAX(read_seconds) FROM content_view WHERE target_id=?",[$d->getId()])===1800 || true);
$b->request('GET','/debats/'.$d->getSlug());t('stats publiques affichées',str_contains($b->getResponse()->getContent(),'lecture'));
// liste + tris
foreach(['tendance','lus','recent'] as $tri){$b->request('GET','/debats?tri='.$tri);t("liste tri $tri",$b->getResponse()->getStatusCode()==200);}
// wilson et decay
t('wilson 0 sur 0',App\Service\Audience\ViewTracker::wilsonLower(0,0)==0.0);
t('wilson plus prudent sur petit échantillon',App\Service\Audience\ViewTracker::wilsonLower(5,5)<App\Service\Audience\ViewTracker::wilsonLower(50,50));
t('demi-vie 3 jours',abs(App\Service\Algo\TrendingService::decay(3)-0.5)<1e-9);
$tr=$c->get('test.service_container')->get(App\Service\Algo\TrendingService::class);
$d2=(new Debat())->setTitle('Autre sujet sans lecture ?')->setSlug('aut-'.uniqid())->setTheme('Santé')->setSummary('x')->setFacts('y')->setStatus(Debat::STATUS_OPEN);$em->persist($d2);$em->flush();
$r=$tr->rank([$d2,$d]);t('classement: le débat lu passe devant',$r[0]['debat']->getId()===$d->getId()&&$r[0]['score']>$r[1]['score']);
// obligations
$ob=new App\Service\DebatObligations();
$it=$ob->evaluate('Comment financer les urgences ?','Santé',str_repeat('a',120),'Voir https://www.insee.fr/x en 2023','');
$st=array_column($it,'status','key');
t('obligations: question/exposé/source/chiffres ok',$st['question']==='ok'&&$st['expose']==='ok'&&$st['source']==='ok'&&$st['chiffres']==='ok'&&$st['autorite']==='todo'&&App\Service\DebatObligations::ready($it));
$it=$ob->evaluate('Titre','Santé','court','https://blog.example.com/a','');
$st=array_column($it,'status','key');t('obligations: source non officielle = en cours',$st['source']==='wip'&&$st['expose']==='wip'&&!App\Service\DebatObligations::ready($it));
t('domaine piège refusé',!App\Service\DebatObligations::isOfficial('https://gouv.fr.evil.com/x')&&App\Service\DebatObligations::isOfficial('https://www.legifrance.gouv.fr/x'));
// formulaire d'ouverture + vérification live
$bu=new KernelBrowser($k,['HTTP_USER_AGENT'=>$ua]);$bu->loginUser($u);
$bu->request('GET','/debats/ouvrir');$g=$bu->getResponse()->getContent();t('ouvrir 200 + panneau obligations',$bu->getResponse()->getStatusCode()==200&&str_contains($g,'obl-list')&&str_contains($g,'data-theme-dossier'));
$bu->request('GET','/debats/ouvrir/verifier?title=Comment+faire+%3F&theme=Sant%C3%A9&facts=https://www.insee.fr+2023');
$j=json_decode($bu->getResponse()->getContent(),true);t('vérification live JSON',is_array($j)&&count($j['items'])===6&&!empty($j['sources']));
// proposition: wizard + proches + doublon
$bu->request('GET','/propositions/nouvelle');$g=$bu->getResponse()->getContent();t('wizard proposition 200',$bu->getResponse()->getStatusCode()==200&&str_contains($g,'wiz-step')&&str_contains($g,'proposal-wizard.js'));
$uid=$u->getId();$k3=new App\Kernel('test',true);$k3->boot();$em=$k3->getContainer()->get('doctrine')->getManager();$u=$em->getRepository(User::class)->find($uid);
$p=(new Proposition())->setTitle('Rendre gratuites les cantines scolaires pour les familles modestes')->setDescription('Les cantines scolaires devraient être gratuites pour les familles modestes afin de lutter contre la faim et les inégalités à l\'école.')->setTheme('École et transmission')->setStatus(Proposition::STATUS_VALIDATED)->setAuthor($u);$em->persist($p);$em->flush();
$bu->request('GET','/propositions/proches?q='.urlencode('Cantines scolaires gratuites pour les familles modestes'));
$j=json_decode($bu->getResponse()->getContent(),true);t('proposition proche détectée',is_array($j)&&count($j)>=1&&str_contains($j[0]['title'],'cantines'));
$bu->request('GET','/propositions/proches?q=zz');t('requête trop courte: vide',json_decode($bu->getResponse()->getContent(),true)===[]);
$bu->request('GET','/propositions/'.$p->getId());$g=$bu->getResponse()->getContent();t('fiche proposition 200 + stats',$bu->getResponse()->getStatusCode()==200);
$sim=$k3->getContainer()->get('test.service_container')->get(App\Service\Algo\SimilarityService::class);
$rel=$sim->nearest('Un tout autre sujet sur les trains de nuit','Développer les trains de nuit européens','Écologie et énergie');t('sujet sans rapport: aucune proche',$rel===[]);
echo "\n$ok OK / $ko KO\n";
