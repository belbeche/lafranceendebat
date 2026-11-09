<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Debat,Argument,Proposition,Revendication};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$u=new User();$u->setEmail('rel'.uniqid().'@orga-test.fr')->setPassword('x')->setRoles(['ROLE_USER']);$u->setFirstName('Rel');$u->setLastName('T');$em->persist($u);
$d=(new Debat())->setTitle('Débat fiabilité test')->setSlug('fiab-'.uniqid())->setTheme(Proposition::THEMES[0])->setSummary(str_repeat('Exposé neutre. ',10))->setFacts('Chiffre https://www.insee.fr/fr/statistiques source officielle')->setStatus('open')->setProposedBy($u);
$em->persist($d);$ar=(new Argument())->setDebat($d)->setAuthor($u)->setStance('pour')->setContent('Selon l\'INSEE, le chiffre est documenté.')->setSourceUrl('https://www.insee.fr/fr/statistiques/1');$em->persist($ar);$em->flush();
$c->request('GET','/debats/'.$d->getSlug());$h=$c->getResponse()->getContent();t('debat 200',$c->getResponse()->getStatusCode()==200);t('fiabilite debat + argument (2 blocs)',substr_count($h,'class="reliab ')>=2);
$e=$em->getRepository(Revendication::class)->findOneBy(['isPublished'=>true]);
if($e){$c->request('GET','/revendications/'.$e->getSlug());$h=$c->getResponse()->getContent();t('revendication fiabilite',$c->getResponse()->getStatusCode()==200&&str_contains($h,'Score calculé automatiquement'));}else echo "skip revendication\n";
$p=$em->getRepository(Proposition::class)->findOneBy(['status'=>'Validée']);
if(!$p){$p=$em->getRepository(Proposition::class)->findOneBy([]);$p->setStatus('Validée');$em->flush();}
$c->request('GET','/propositions/'.$p->getId());$h=$c->getResponse()->getContent();t('proposition fiabilite',$c->getResponse()->getStatusCode()==200&&str_contains($h,'Score calculé automatiquement'));
echo "$ok ok / $ko ko\n";
