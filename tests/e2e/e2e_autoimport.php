<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,ActorContact};
use App\Service\PublicActorImporter;
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$imp=$c->getContainer()->get(PublicActorImporter::class);
$f=json_decode('{"nom":"Mairie de Vesoul","adresse_courriel":"mairie@vesoul.fr","url_service_public":"https://lannuaire.service-public.gouv.fr/x","pivot":"[{\"type_service_local\":\"mairie\"}]","adresse":"[{\"type_adresse\":\"Adresse\",\"numero_voie\":\"1 place\",\"code_postal\":\"70000\",\"nom_commune\":\"Vesoul\"}]"}',true);
$m=$imp->mapAnnuaire($f);t('map annuaire',$m['categorie']==='commune'&&$m['ville']==='Vesoul'&&$m['email']==='mairie@vesoul.fr');
t('map depute sans mail ecarte',$imp->mapDepute(['uid'=>'PA1','etatCivil'=>['ident'=>['nom'=>'X']],'adresses'=>['adresse'=>[]]])===null);
t('map depute mail perso ecarte',$imp->mapDepute(['uid'=>'PA1','etatCivil'=>['ident'=>['nom'=>'X']],'adresses'=>['adresse'=>[['typeLibelle'=>'Mèl','valElec'=>'x@gmail.com']]]])===null);
$d=$imp->mapDepute(['uid'=>'PA1','etatCivil'=>['ident'=>['civ'=>'M.','prenom'=>'A','nom'=>'B']],'adresses'=>['adresse'=>[['typeLibelle'=>'Mèl','valElec'=>'a.b@assemblee-nationale.fr']]]]);
t('map depute ok',$d&&$d['source_url']==='https://www2.assemblee-nationale.fr/deputes/fiche/OMC_PA1'&&$d['categorie']==='parlementaire');
$h=array_flip(['État','Courrier électronique','Qualité','Prénom usuel','Nom usuel']);
t('senateur ancien ecarte',$imp->mapSenateur(['ANCIEN','a@senat.fr','M.','A','B'],$h)===null);
t('senateur non public ecarte',$imp->mapSenateur(['ACTIF','Non public','M.','A','B'],$h)===null);
t('senateur actif ok',($imp->mapSenateur(['ACTIF','a@senat.fr','M.','A','B'],$h)['email']??'')==='a@senat.fr');
$a=new User();$a->setEmail('ad'.uniqid().'@t.fr');$a->setPassword('x');$a->setFirstName('A');$a->setLastName('B');$a->setRoles(['ROLE_ADMIN']);$em->persist($a);$em->flush();
$c->loginUser($a);$c->request('GET','/admin/acteurs-publics');$h=$c->getResponse()->getContent();
t('page admin boutons',$c->getResponse()->getStatusCode()==200&&str_contains($h,'Importer maintenant')&&str_contains($h,'Simuler'));
preg_match('/name="_token" value="([^"]+)"/',$h,$mm);
$before=$em->getRepository(ActorContact::class)->count([]);
$c->request('POST','/admin/acteurs-publics/importer-auto',['_token'=>$mm[1],'source'=>'senat','mode'=>'simuler']);
t('simulation redirige',$c->getResponse()->isRedirect());
$em->clear();t('simulation n ecrit rien',$em->getRepository(ActorContact::class)->count([])===$before);
$c->request('POST','/admin/acteurs-publics/importer-auto',['_token'=>'bad','source'=>'senat','mode'=>'simuler']);t('csrf refuse',$c->getResponse()->getStatusCode()==403);
echo "\n$ok OK / $ko KO\n";
