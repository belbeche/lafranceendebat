<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,RoleRequest,ServiceOffer};
$k=new App\Kernel('test',true);$k->boot();$c=new Symfony\Bundle\FrameworkBundle\KernelBrowser($k);$c->setServerParameter('HTTP_ACCEPT_LANGUAGE','fr');
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$em=$k->getContainer()->get('doctrine')->getManager();
$u=new User();$u->setEmail('a'.uniqid().'@t.fr');$u->setPassword('x');$u->setFirstName('Zoe');$u->setLastName('Mediatrice'.uniqid());$u->setRoles(['ROLE_USER','ROLE_ASSOCIATION']);$em->persist($u);$em->flush();
$c->request('GET','/missions');$h=$c->getResponse()->getContent();
t('page 200',$c->getResponse()->getStatusCode()==200);
t('lien inscription association',str_contains($h,'profil=association'));
t('lien inscription elu',str_contains($h,'profil=elu'));
t('lien inscription expert',str_contains($h,'profil=expert'));
t('ligne aucun acteur utile',str_contains($h,'Inscrivez-vous et décrivez vos compétences'));
echo "\n$ok OK / $ko KO\n";
