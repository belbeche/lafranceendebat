<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Proposition,ActorEndorsement};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=fn($n,$b)=>print(($b?'OK ':'KO ').$n."\n");
foreach(['/missions','/tutoriels','/'] as $u){$c->request('GET',$u);$ok("$u ".$c->getResponse()->getStatusCode(),$c->getResponse()->getStatusCode()==200);}
$em=$c->getContainer()->get('doctrine')->getManager();
$u=new User();$u->setEmail('elu'.uniqid().'@mairie-test.fr')->setPassword('x')->setRoles(['ROLE_USER','ROLE_ELU']);
foreach(['setFirstName','setLastName'] as $m) if(method_exists($u,$m)) $u->$m('Test');
$em->persist($u);
$p=$em->getRepository(Proposition::class)->findOneBy(['status'=>'Validée']);
if(!$p){$p=new Proposition();$p->setTitle('Test avis')->setDescription(str_repeat('Description utile. ',10))->setTheme('Santé')->setStatus('Validée')->setAuthor($u);$em->persist($p);}
$em->flush();
$c->loginUser($u);
$c->request('GET','/propositions/'.$p->getId());$h=$c->getResponse()->getContent();$ok('form avis visible',str_contains($h,'Publier mon avis'));
preg_match('/name="_token" value="([^"]+)"[^>]*>\s*<div class="mb-3"><label class="form-label" for="en-stance"/',$h,$m);$tok=$m[1]??'';$ok('token',$tok!=='');
$c->request('POST','/propositions/'.$p->getId().'/avis-acteur',['_token'=>$tok,'stance'=>'reserve','reason'=>'Court']);
$ok('avis trop court refuse',$em->getRepository(ActorEndorsement::class)->count([])==0);
$c->request('POST','/propositions/'.$p->getId().'/avis-acteur',['_token'=>$tok,'stance'=>'reserve','reason'=>'Je soutiens sous reserve de financement clair et de calendrier.']);
$ok('avis enregistre',$em->getRepository(ActorEndorsement::class)->count([])==1);
$c->request('GET','/propositions/'.$p->getId());$ok('avis affiche',str_contains($c->getResponse()->getContent(),'Approuve avec réserves'));
$c->request('POST','/propositions/'.$p->getId().'/avis-acteur',['_token'=>$tok,'stance'=>'approve','reason'=>'Version corrigee de mon avis, argumentee et sourcee.']);
$ok('un seul avis par acteur',$em->getRepository(ActorEndorsement::class)->count([])==1);
$c2=$k->getContainer()->get('test.client');
