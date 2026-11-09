<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Service\DonationReceiptService;use App\Entity\User;
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
t('lettres 1250,50',DonationReceiptService::amountInWords(125050)==='mille deux cent cinquante euros et cinquante centimes');
t('lettres 80 et 71',DonationReceiptService::amountInWords(8000)==='quatre-vingts euros'&&DonationReceiptService::amountInWords(7100)==='soixante et onze euros');
t('lettres 1 euro',DonationReceiptService::amountInWords(100)==='un euro');
$em=$c->getContainer()->get('doctrine')->getManager();$h=$c->getContainer()->get('security.user_password_hasher');
$a=new User();$a->setEmail('adm'.uniqid().'@t.fr')->setRoles(['ROLE_ADMIN']);$a->setPassword($h->hashPassword($a,'x'));$a->setFirstName('A');$a->setLastName('D');$em->persist($a);$em->flush();
$c->loginUser($a,'main');
$c->request('GET','/admin/recus-fiscaux');$body=$c->getResponse()->getContent();
$enabled=getenv('DONATION_RECEIPTS_ENABLED')==='1';
if(!$enabled){t('désactivé par défaut',$c->getResponse()->getStatusCode()==200&&str_contains($body,'Fonction désactivée')&&str_contains($body,'Une entreprise individuelle ne peut pas'));
 $c->request('GET','/admin/recus-fiscaux/1.pdf');t('pdf 404 si désactivé',$c->getResponse()->getStatusCode()==404);}
else{
 t('formulaire visible',str_contains($body,'Créer le reçu'));
 $tok=$c->getCrawler()->filter('main input[name=_token]')->attr('value');
 $c->request('POST','/admin/recus-fiscaux',['_token'=>$tok,'donor'=>'Jeanne Martin','address'=>'12 rue des Lilas 14000 Caen','amount'=>'50','date'=>'2026-10-01','mode'=>'virement']);
 $c->followRedirect();t('reçu créé',str_contains($c->getResponse()->getContent(),'RF-2026-'));
 $r=$em->getRepository(App\Entity\DonationReceipt::class)->findOneBy(['donorName'=>'Jeanne Martin']);
 $c->request('GET','/admin/recus-fiscaux/'.$r->getId().'.pdf');t('pdf valide',str_starts_with($c->getResponse()->getContent(),'%PDF')&&$c->getResponse()->headers->get('Content-Type')==='application/pdf');
 $c->request('POST','/admin/recus-fiscaux',['_token'=>$tok,'donor'=>'X Y Z','address'=>'adresse complète 1','amount'=>'50','date'=>'2999-01-01','mode'=>'virement']);t('date future refusée',str_contains($c->getResponse()->getContent(),'futur'));
}
echo "$ok ok / $ko ko\n";
