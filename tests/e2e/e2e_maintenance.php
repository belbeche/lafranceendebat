<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\User;
$k=new App\Kernel('test',true);$k->boot();
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$k->getContainer()->get('doctrine')->getManager();$m=new App\Service\MaintenanceMode($k->getProjectDir().'/var/maintenance.json');$m->disable();
$new=fn()=>new Symfony\Bundle\FrameworkBundle\KernelBrowser($k);
$c=$new();$c->request('GET','/sante');t('sante ok hors maintenance',$c->getResponse()->getStatusCode()==200&&str_contains($c->getResponse()->getContent(),'"status":"ok"'));
$c->request('GET','/');t('accueil ouvert',$c->getResponse()->getStatusCode()==200);
$m->enable('Mise à jour test',15);
$c=$new();$c->request('GET','/');t('visiteur: 503',$c->getResponse()->getStatusCode()==503);
t('retry-after',$c->getResponse()->headers->get('Retry-After')==='300');t('message affiche',str_contains($c->getResponse()->getContent(),'Mise à jour test'));
$c->request('GET','/login');t('login reste ouvert',$c->getResponse()->getStatusCode()==200);
$c->request('GET','/sante');t('sante: 503 maintenance',$c->getResponse()->getStatusCode()==503&&str_contains($c->getResponse()->getContent(),'maintenance'));
$u=new User();$u->setEmail('mt'.uniqid().'@t.fr');$u->setPassword('x');$u->setFirstName('A');$u->setLastName('B');$u->setRoles(['ROLE_ADMIN']);$em->persist($u);$em->flush();
$c=$new();$c->loginUser($u);$c->request('GET','/');t('admin passe',$c->getResponse()->getStatusCode()==200);
$cit=new User();$cit->setEmail('mc'.uniqid().'@t.fr');$cit->setPassword('x');$cit->setFirstName('A');$cit->setLastName('C');$cit->setRoles(['ROLE_USER']);$em->persist($cit);$em->flush();
$c=$new();$c->loginUser($cit);$c->request('GET','/propositions');t('citoyen connecte: 503',$c->getResponse()->getStatusCode()==503);
$c=$new();$c->loginUser($u);$c->request('GET','/admin/rubriques');$h=$c->getResponse()->getContent();t('bloc maintenance admin',str_contains($h,'Mode maintenance : ACTIF'));
preg_match('~maintenance/mode"[^>]*>\s*<input type="hidden" name="_token" value="([^"]+)"~',$h,$mm);
$c->request('POST','/admin/maintenance/mode',['_token'=>$mm[1]??'x','etat'=>'off']);
$m2=new App\Service\MaintenanceMode($k->getProjectDir().'/var/maintenance.json');t('rouvert via admin',!$m2->isOn());
$m->disable();
echo "\n$ok OK / $ko KO\n";
