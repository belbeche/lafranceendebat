<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Debat};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$c->getContainer()->get('doctrine')->getManager();
$mk=function($role,$p)use($em){$u=new User();$u->setEmail($p.uniqid().'@orga-test.fr')->setPassword('x')->setRoles($role);$u->setFirstName($p);$u->setLastName('T');$em->persist($u);$em->flush();return $u;};
$au=$mk(['ROLE_USER'],'aut');$ot=$mk(['ROLE_USER'],'oth');$mo=$mk(['ROLE_MODERATOR'],'mod');
$d=(new Debat())->setTitle('Question de test pour la gestion')->setSlug('test-gestion-'.uniqid())->setTheme(App\Entity\Proposition::THEMES[0])->setSummary(str_repeat('Exposé neutre. ',10))->setFacts('Chiffre 2025 https://www.insee.fr/fr/statistiques test source')->setStatus('open')->setProposedBy($au);
$em->persist($d);$em->flush();$u='/debats/'.$d->getSlug().'/gerer';
$c->request('GET',$u);t('anonyme redirige',$c->getResponse()->getStatusCode()==302);
$c->loginUser($ot);$c->request('GET',$u);t('autre user 403',$c->getResponse()->getStatusCode()==403);
$c->loginUser($au);$c->request('GET',$u);$h=$c->getResponse()->getContent();t('auteur accede',$c->getResponse()->getStatusCode()==200&&!str_contains($h,'name="status"'));
preg_match('/name="_token" value="([^"]+)"/',$h,$m);
$post=['_token'=>$m[1],'title'=>'Question de test modifiée','theme'=>$d->getTheme(),'summary'=>str_repeat('Nouvel exposé neutre. ',8),'facts'=>'Source mise à jour https://www.insee.fr/fr/statistiques/1','authority'=>'','status'=>'closed'];
$c->request('POST',$u,$post+[]);$em->clear();$d=$em->getRepository(Debat::class)->find($d->getId());
t('auteur modifie titre',$d->getTitle()==='Question de test modifiée');t('auteur ne change pas le statut',$d->getStatus()==='open');t('updatedAt rempli',$d->getUpdatedAt()!==null);
$c->request('GET','/debats/'.$d->getSlug());t('page affiche modifie le',str_contains($c->getResponse()->getContent(),'Modifié le')&&str_contains($c->getResponse()->getContent(),'Gérer ce débat'));
$post['summary']=str_repeat('Texte ',20).' espèce de connard';$c->request('POST',$u,$post);$em->clear();t('insulte refusee',!str_contains($em->getRepository(Debat::class)->find($d->getId())->getSummary(),'connard'));
$c->loginUser($mo);$c->request('GET',$u);$h=$c->getResponse()->getContent();t('moderateur voit statut',str_contains($h,'name="status"'));preg_match('/name="_token" value="([^"]+)"/',$h,$m);
$post=['_token'=>$m[1],'title'=>'Question de test modifiée','theme'=>$d->getTheme(),'summary'=>str_repeat('Nouvel exposé neutre. ',8),'facts'=>'Source https://www.insee.fr/fr/statistiques/1 ok','authority'=>'','status'=>'closed','closesAt'=>'2026-12-31','outcome'=>'Compte rendu publié.'];
$c->request('POST',$u,$post);$em->clear();$d=$em->getRepository(Debat::class)->find($d->getId());
t('moderateur clot',$d->getStatus()==='closed'&&$d->getOutcome()==='Compte rendu publié.'&&$d->getClosesAt()->format('Y-m-d')==='2026-12-31');
echo "$ok ok / $ko ko\n";
