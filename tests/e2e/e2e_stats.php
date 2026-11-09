<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k=new App\Kernel('test',true);$k->boot();
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$k->getContainer()->get('doctrine')->getManager();
$logger=new class extends Psr\Log\AbstractLogger{public array $w=[];public function log($l,$m,array $c=[]):void{$this->w[]=(string)$m;}};
$st=new App\Service\AdminStats($em,$logger);$all=$st->all();
t('aucun bloc en erreur: '.implode(' | ',$logger->w),$logger->w===[]);
foreach(['members','signups','content','top','contributors','audience','activity','security','lettre','surveys','commerce','agenda','moderation'] as $b)t("bloc $b renseigné",array_key_exists($b,$all)&&($all[$b]!==[]||in_array($b,['top','contributors','security','activity','commerce'],true)));
t('membres: total cohérent',$all['members']['total']===$em->getRepository(User::class)->count([]));
t('inscriptions: 30 jours',count($all['signups']['days'])===30);
$mk=function($r)use($em){$u=new User();$u->setEmail('st'.uniqid().'@t.fr')->setPassword('x')->setFirstName('S')->setLastName('T')->setRoles($r);$em->persist($u);$em->flush();return $u;};
$adm=$mk(['ROLE_ADMIN']);$cit=$mk(['ROLE_USER']);
$b=new KernelBrowser($k);$b->loginUser($adm);$b->request('GET','/admin/statistics');$h=$b->getResponse()->getContent();
t('page statistiques 200',$b->getResponse()->getStatusCode()==200);
foreach(['Membres','Participation','Audience','Lettre, enquêtes, agenda','Paiements et sécurité','Inscriptions par jour'] as $x)t("section « $x »",str_contains($h,$x));
$b->request('GET','/admin/statistics.csv');$csv=$b->getInternalResponse()->getContent();t('export CSV',str_contains($csv,'members.total')&&str_contains($csv,'Indicateur'));
$bm=new KernelBrowser($k);$bm->loginUser($cit);$bm->request('GET','/admin/statistics');t('membre refusé',$bm->getResponse()->getStatusCode()==403);
echo "\n$ok OK / $ko KO\n";
