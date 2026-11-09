<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\ActorContact;
use Symfony\Component\HttpClient\MockHttpClient;use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Console\Tester\CommandTester;
$k=new App\Kernel('test',true);$k->boot();$ct=$k->getContainer()->get('test.service_container');
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$ct->get('doctrine')->getManager();
$u=uniqid();
$rec=fn($mail,$n)=>['nom'=>"Mairie - Mairie - $n",'adresse_courriel'=>$mail,'url_service_public'=>"https://lannuaire.service-public.gouv.fr/bretagne/finistere/$n",'telephone'=>'[{"valeur": "02 98 91 50 32", "description": ""}]','site_internet'=>'[{"libelle": "", "valeur": "https://guiler-sur-goyen.bzh/"}]','pivot'=>'[{"type_service_local": "mairie", "code_insee_commune": ["29070"]}]','adresse'=>'[{"type_adresse": "Adresse", "complement1": "", "numero_voie": "2 Hent-Park-Meilar", "code_postal": "29710", "nom_commune": "Guiler-sur-Goyen"}]'];
$body=json_encode(['total_count'=>3,'results'=>[$rec("mairie$u@guiler-sur-goyen.bzh","a$u"),$rec("perso$u@orange.fr","b$u"),$rec("mairie$u@guiler-sur-goyen.bzh","a$u")]]);
$cmd=new App\Command\ImportAnnuaireCommand(new MockHttpClient(new MockResponse($body)),$ct->get(App\Service\ActorImporter::class),$em);
$tester=new CommandTester($cmd);$tester->execute(['-r'=>'Mairie','-d'=>'29']);
$out=$tester->getDisplay();echo $out;
$a=$em->getRepository(ActorContact::class)->findOneBy(['email'=>"mairie$u@guiler-sur-goyen.bzh"]);
t('contact ajoute avec coordonnees',$a&&$a->getPhone()==='02 98 91 50 32'&&$a->getPostalCode()==='29710'&&$a->getCategory()==='commune'&&str_starts_with($a->getWebsite(),'https://'));
t('source = fiche officielle',$a&&str_contains($a->getSourceUrl(),'lannuaire.service-public.gouv.fr'));
t('adresse perso refusee',!$em->getRepository(ActorContact::class)->findOneBy(['email'=>"perso$u@orange.fr"]));
t('doublon ignore',str_contains($out,'refusees : 1'));
$tester->execute(['-d'=>'ZZ']);t('departement invalide refuse',$tester->getStatusCode()!==0);
echo "$ok ok / $ko ko\n";
