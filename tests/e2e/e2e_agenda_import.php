<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{Event,User};
use App\Service\Agenda\{EventVerifier,NationalEventImporter};
use Symfony\Component\HttpClient\MockHttpClient;use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k=new App\Kernel('test',true);$k->boot();
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$k->getContainer()->get('doctrine')->getManager();
$y=(int)date('Y');$next=date('Y-m-d',strtotime('+20 days'));$far=date('Y-m-d',strtotime('+3 years'));
$mod=new App\Service\ContentModerator(['connard','salope']);
$calls=[];
$mk=function(string $ics='') use(&$calls,$y,$next){return new MockHttpClient(function($m,$u) use(&$calls,$y,$next,$ics){$calls[]=$u;
  if(str_contains($u,'calendrier.api.gouv.fr')) return new MockResponse(json_encode([$next=>'Jour test A','2020-01-01'=>'Vieux jour']),['response_headers'=>['content-type: application/json']]);
  return new MockResponse($ics,['response_headers'=>['content-type: text/calendar']]);});};
$ics="BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:abc-1@assemblee\r\nSUMMARY:Séance publique de test\r\nDTSTART:".date('Ymd',strtotime('+30 days'))."T140000Z\r\nLOCATION:Paris\r\nURL:https://www.assemblee-nationale.fr/x\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:bad-2\r\nSUMMARY:Evenement avec lien douteux\r\nDTSTART:".date('Ymd',strtotime('+31 days'))."\r\nURL:https://site-douteux.example/x\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:far-3\r\nSUMMARY:Trop loin dans le temps\r\nDTSTART:".str_replace('-','',$far)."\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:bad-4\r\nSUMMARY:Ce connard organise\r\nDTSTART:".date('Ymd',strtotime('+32 days'))."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
$rep=sys_get_temp_dir().'/agenda_rep_'.uniqid().'.json';
$feedUrl='https://www.assemblee-nationale.fr/agenda.ics';
$imp=new NationalEventImporter($em,$mk($ics),new EventVerifier($mod),new Psr\Log\NullLogger(),$feedUrl.',https://evil.example/f.ics','/home/claude/app/config/evenements_nationaux.yaml',$rep);
$em->createQuery('DELETE FROM App\Entity\Event e WHERE e.source IS NOT NULL')->execute();
$before=$em->getRepository(Event::class)->count([]);
$r=$imp->run(false,1);
$after=$em->getRepository(Event::class)->count([]);
t("créés > 0 ({$r['created']})",$r['created']>0&&$after-$before===$r['created']);
$a=$em->getRepository(Event::class)->findOneBy(['source'=>'feries','name'=>'Jour férié : Jour test A']);
t('jour férié publié (upcoming) avec source',$a&&$a->getStatus()==='upcoming'&&$a->getKind()==='ferie'&&$a->getVerifiedAt()!==null);
t('date passée ignorée',$em->getRepository(Event::class)->findOneBy(['name'=>'Jour férié : Vieux jour'])===null);
$ev=$em->getRepository(Event::class)->findOneBy(['externalId'=>'abc-1@assemblee']);
t('événement ICS officiel importé',$ev!==null&&$ev->getKind()==='institution');
t('lien hors liste refusé',$em->getRepository(Event::class)->findOneBy(['externalId'=>'bad-2'])===null&&count(array_filter($r['rejected'],fn($x)=>str_contains($x,'douteux')))===1);
t('date trop lointaine ignorée',$em->getRepository(Event::class)->findOneBy(['externalId'=>'far-3'])===null);
t('terme interdit refusé',$em->getRepository(Event::class)->findOneBy(['externalId'=>'bad-4'])===null);
t('flux d\'un hôte non autorisé jamais appelé',!in_array('https://evil.example/f.ics',$calls,true)&&count(array_filter($r['errors'],fn($x)=>str_contains($x,'evil.example')))===1);
$nat=$em->getRepository(Event::class)->findBy(['source'=>'fixe']);
t('journées nationales à date fixe ('.count($nat).')',count($nat)>=1&&$nat[0]->getKind()==='national');
// idempotence
$r2=$imp->run(false,1);
t('2e exécution : aucun doublon',$r2['created']===0&&$r2['updated']===0&&$em->getRepository(Event::class)->count([])===$after);
// mise à jour si la source change le titre
$ics2=str_replace('Séance publique de test','Séance publique modifiée',$ics);
$imp2=new NationalEventImporter($em,$mk($ics2),new EventVerifier($mod),new Psr\Log\NullLogger(),$feedUrl,'/home/claude/app/config/evenements_nationaux.yaml',$rep);
$r3=$imp2->run(false,1);$em->clear();
t('titre modifié à la source : mis à jour',$r3['updated']===1&&$em->getRepository(Event::class)->findOneBy(['externalId'=>'abc-1@assemblee'])->getName()==='Séance publique modifiée');
t('rapport enregistré',($imp2->lastReport()['updated']??null)===1);
// dry-run
$n=$em->getRepository(Event::class)->count([]);$imp3=new NationalEventImporter($em,$mk(''),new EventVerifier($mod),new Psr\Log\NullLogger(),'','/home/claude/app/config/evenements_nationaux.yaml',$rep);
$imp3->run(true,3);$em->clear();t('dry-run n\'écrit rien',$em->getRepository(Event::class)->count([])===$n);
// source en panne : les autres sources sont traitées
$down=new MockHttpClient(fn()=>new MockResponse('',['http_code'=>503]));
$imp4=new NationalEventImporter($em,$down,new EventVerifier($mod),new Psr\Log\NullLogger(),'','/home/claude/app/config/evenements_nationaux.yaml',$rep);
$r4=$imp4->run(true,1);t('source en panne : erreur signalée, pas de plantage',count($r4['errors'])===1);
@unlink($rep);
// pages
$b=new KernelBrowser($k);$b->request('GET','/agenda');$h=$b->getResponse()->getContent();
t('agenda 200 + jour férié visible sans heure',$b->getResponse()->getStatusCode()==200&&str_contains($h,'Jour test A')&&str_contains($h,'Jour férié'));
t('hôtes autorisés',EventVerifier::hostAllowed('https://data.gouv.fr/x')&&EventVerifier::hostAllowed('https://www.senat.fr/')&&!EventVerifier::hostAllowed('http://www.senat.fr/')&&!EventVerifier::hostAllowed('https://gouv.fr.evil.com/')&&!EventVerifier::hostAllowed('https://evilgouv.fr/'));
$u=new User();$u->setEmail('ag'.uniqid().'@t.fr')->setPassword('x')->setFirstName('A')->setLastName('B')->setRoles(['ROLE_ADMIN']);$em->persist($u);$em->flush();
$ba=new KernelBrowser($k);$ba->loginUser($u);$ba->request('GET','/admin/agenda-import');t('admin import 200',$ba->getResponse()->getStatusCode()==200&&str_contains($ba->getResponse()->getContent(),'app:agenda:importer'));
echo "\n$ok OK / $ko KO\n";
