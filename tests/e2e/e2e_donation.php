<?php
// Scénario AVEC reçu fiscal (organisme éligible). Lancer avec DONATIONS_OPEN=1 DONATION_RECEIPTS_ENABLED=1 et les ASSO_*. Pour une micro-entreprise : voir e2e_contribution.php.
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\Donation;use App\Service\Payment\Testing\FakeGateway;
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','fr'));
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$c->request('GET','/soutenir/contribution');
if(getenv('DONATION_RECEIPTS_ENABLED')!=='1'){t('/soutenir/contribution 404 si désactivé',$c->getResponse()->getStatusCode()==404);echo "$ok ok / $ko ko\n";exit;}
t('formulaire 200',$c->getResponse()->getStatusCode()==200);
$tok=$c->getCrawler()->filter('main input[name=_token]')->attr('value');
$p=['_token'=>$tok,'name'=>'Jeanne '.($u=uniqid()),'address'=>'12 rue des Lilas 14000 Caen','email'=>$u.'@example.org','amount'=>'30'];
$c->request('POST','/soutenir/contribution',$p);t('sans déclaration refusé',str_contains($c->getResponse()->getContent(),'Confirmez'));
$c->request('POST','/soutenir/contribution',array_merge($p,['no_political'=>'1','amount'=>'1']));t('montant trop bas refusé',str_contains($c->getResponse()->getContent(),'compris entre'));
FakeGateway::$calls=[];
$c->request('POST','/soutenir/contribution',$p+['no_political'=>'1']);t('redirige vers Stripe',$c->getResponse()->isRedirect()&&str_contains($c->getResponse()->headers->get('Location'),'checkout.stripe.test/don/'));
$em=$c->getContainer()->get('doctrine')->getManager();$em->clear();$d=$em->getRepository(Donation::class)->findOneBy(['donorEmail'=>$u.'@example.org'],['id'=>'DESC']);
t('don créé en attente, 3000 centimes',$d&&$d->getStatus()==='attente_paiement'&&$d->getAmountCents()===3000&&in_array('donation:'.$d->getId().':3000',FakeGateway::$calls));
$c->request('GET','/soutenir/recu/'.$d->getToken().'.pdf');t('pas de reçu avant paiement',$c->getResponse()->getStatusCode()==404);
$evt=json_encode(['type'=>'checkout.session.completed','data'=>['object'=>['id'=>$d->getStripeSessionId(),'payment_status'=>'paid','amount_total'=>3000,'metadata'=>['donation_id'=>(string)$d->getId()]]]]);
$ts=time();$sig='t='.$ts.',v1='.hash_hmac('sha256',$ts.'.'.$evt,'whsec_test_secret');
$c->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>$sig],$evt);
$em->clear();$d=$em->find(Donation::class,$d->getId());
t('webhook: don payé + reçu',$d->getStatus()==='payee'&&$d->getReceipt()&&str_starts_with($d->getReceipt()->getNumber(),'RF-'));
$c->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>$sig],$evt);$em->clear();
$n=$em->getRepository(App\Entity\DonationReceipt::class)->count(['donorName'=>'Jeanne '.$u]);t('webhook rejoué: un seul reçu',$n===1);
$c->request('GET','/soutenir/recu/'.$d->getToken().'.pdf');t('reçu PDF téléchargeable',str_starts_with($c->getResponse()->getContent(),'%PDF'));
$c->request('GET','/soutenir/merci/'.$d->getToken());t('page merci',str_contains($c->getResponse()->getContent(),'Télécharger'));
$evt2=json_encode(['type'=>'checkout.session.completed','data'=>['object'=>['id'=>'cs_don_x','payment_status'=>'paid','amount_total'=>1,'metadata'=>['donation_id'=>'99999']]]]);
$sig2='t='.$ts.',v1='.hash_hmac('sha256',$ts.'.'.$evt2,'whsec_test_secret');$c->request('POST','/webhook/stripe',[],[],['HTTP_STRIPE_SIGNATURE'=>$sig2],$evt2);t('don inconnu ignoré',$c->getResponse()->getStatusCode()==200);
echo "$ok ok / $ko ko\n";
