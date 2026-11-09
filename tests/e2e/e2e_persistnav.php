<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\User;
$k=new App\Kernel('test',true);$k->boot();
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$k->getContainer()->get('doctrine')->getManager();
$mk=function($r) use($em){$u=new User();$u->setEmail('pn'.uniqid().'@t.fr');$u->setPassword('x');$u->setFirstName('P');$u->setLastName('N');$u->setRoles($r);$em->persist($u);$em->flush();return $u;};
$cit=$mk(['ROLE_USER']);$adm=$mk(['ROLE_ADMIN']);$act=$mk(['ROLE_USER','ROLE_ACTOR']);
$get=function($u,$path) use($k){$c=new Symfony\Bundle\FrameworkBundle\KernelBrowser($k);if($u)$c->loginUser($u);$c->request('GET',$path);return [$c->getResponse()->getStatusCode(),$c->getResponse()->getContent()];};
foreach(['/profil','/profil/modifier','/profil/connexions','/messages','/notifications','/propositions/mes-propositions','/communaute','/missions/annonces','/services/mes-commandes'] as $p){[$s,$h]=$get($cit,$p);t("menu visible $p ($s)",$s==200&&substr_count($h,'class="sp-nav"')===1);}
[$s,$h]=$get($cit,'/mon-espace');t('hub: un seul menu',$s==200&&substr_count($h,'class="sp-nav"')===1);
[$s,$h]=$get(null,'/communaute');t('visiteur anonyme: pas de menu',$s==200&&!str_contains($h,'class="sp-nav"'));
[$s,$h]=$get($act,'/espace-acteur');t("espace acteur: un menu acteur ($s)",$s==200&&substr_count($h,'class="sp-nav"')===1&&str_contains($h,'Candidatures et demandes'));
[$s,$h]=$get($adm,'/admin/rubriques');t('admin natif: un seul menu',$s==200&&substr_count($h,'aria-label="Administration"')===1&&!str_contains($h,'adm-shell'));
foreach(['/admin/journal','/admin/visitors/'] as $p){[$s,$h]=$get($adm,$p);t("admin menu visible $p ($s)",$s==200&&substr_count($h,'aria-label="Administration"')===1&&str_contains($h,'adm-shell'));}
[$s,$h]=$get($adm,'/admin/moderation');t('moderation: un seul menu',substr_count($h,'aria-label="Administration"')===1);
[$s,$h]=$get(null,'/');t('accueil sans menu',!str_contains($h,'sp-nav')&&!str_contains($h,'adm-shell'));
echo "\n$ok OK / $ko KO\n";
