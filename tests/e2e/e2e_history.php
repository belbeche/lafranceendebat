<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Debat,Argument,ContentRevision};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();$h=$c->getContainer()->get('security.user_password_hasher');
$u=new User();$u->setEmail('hist'.uniqid().'@test.fr');$u->setPassword($h->hashPassword($u,'x'));$u->setFirstName('A');$u->setLastName('B');$em->persist($u);
$d=new Debat();$d->setTitle('Debat histoire '.uniqid());$d->setTheme('Santé');$d->setSummary(str_repeat('Texte initial. ',12));$d->setFacts('Donnees officielles disponibles sur https://www.insee.fr/fr/accueil pour verification.');$d->setSlug('debat-histoire-'.uniqid());$d->setStatus(Debat::STATUS_OPEN);$d->setProposedBy($u);
$ref=new ReflectionProperty($d,'createdAt');$ref->setValue($d,new DateTimeImmutable('-2 days'));
$em->persist($d);$em->flush();
$c->loginUser($u);
$c->request('GET','/debats/'.$d->getSlug().'/gerer');t('gerer 200',$c->getResponse()->getStatusCode()==200);
$f=$c->getCrawler()->filter('form')->reduce(fn($n)=>$n->filter('textarea')->count()>0)->first()->form();
$vals=$f->getPhpValues();$vals['debat']['summary']=str_repeat('Texte corrige apres relecture. ',10);
$c->request('POST','/debats/'.$d->getSlug().'/gerer',$vals);
$n=$em->getRepository(ContentRevision::class)->count(['type'=>'debat','refId'=>$d->getId()]);
t('revision enregistree',$n===1);
$c->request('GET','/historique/debat/'.$d->getId());t('historique public 200',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'Texte initial'));
$c->request('GET','/profil');t('profil liste Mes debats',str_contains($c->getResponse()->getContent(),'Mes débats')&&str_contains($c->getResponse()->getContent(),'Gérer ce débat'));
$c->request('GET','/historique/debat/999999');t('historique inconnu 404',$c->getResponse()->getStatusCode()==404);
echo "$ok ok / $ko ko\n";
