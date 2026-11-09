<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\NewsletterSubscriber; use App\Service\Ses\SnsVerifier;
$ok=0;$ko=0; function t($n,$c){global $ok,$ko; if($c){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$key=openssl_pkey_new(['private_key_bits'=>2048]); $csr=openssl_csr_new(['commonName'=>'sns'],$key); $x=openssl_csr_sign($csr,null,$key,1); openssl_x509_export($x,$pem);
class FakeV extends SnsVerifier { public $pem; protected function fetchCertificate(string $u): ?string { return $this->pem; } }
$topic='arn:aws:sns:eu-north-1:501250588657:ses-events';
$v=new FakeV(new Symfony\Component\HttpClient\MockHttpClient(),$topic); $v->pem=$pem;
function mk($key,$topic,$msg,$ts=null,$ver='2'){ $m=['Type'=>'Notification','MessageId'=>'1','TopicArn'=>$topic,'Message'=>$msg,'Timestamp'=>$ts??gmdate('Y-m-d\TH:i:s.000\Z'),'SignatureVersion'=>$ver,'SigningCertURL'=>'https://sns.eu-north-1.amazonaws.com/cert.pem'];
 $s="Message\n{$m['Message']}\nMessageId\n1\nTimestamp\n{$m['Timestamp']}\nTopicArn\n$topic\nType\nNotification\n"; openssl_sign($s,$sig,$key,$ver==='2'?OPENSSL_ALGO_SHA256:OPENSSL_ALGO_SHA1); $m['Signature']=base64_encode($sig); return $m; }
$m=mk($key,$topic,'{"notificationType":"Bounce"}'); t('signature valide',$v->verify($m));
$b=$m; $b['Message']='{"notificationType":"Complaint"}'; t('message altere refuse',!$v->verify($b));
$b=$m; $b['TopicArn']='arn:aws:sns:eu-north-1:1:autre'; t('mauvaise rubrique refusee',!$v->verify($b));
$b=$m; $b['SigningCertURL']='https://evil.example.com/cert.pem'; t('certificat non amazon refuse',!$v->verify($b));
$b=$m; $b['SigningCertURL']='https://sns.eu-north-1.amazonaws.com.evil.com/c.pem'; t('sous-domaine piege refuse',!$v->verify($b));
t('http refuse',!SnsVerifier::isAmazonCertUrl('http://sns.eu-north-1.amazonaws.com/c.pem'));
$old=mk($key,$topic,'{}',gmdate('Y-m-d\TH:i:s.000\Z',time()-7200)); t('message ancien refuse',!$v->verify($old));
$v2=new FakeV(new Symfony\Component\HttpClient\MockHttpClient(),''); $v2->pem=$pem; t('non configure refuse',!$v2->verify($m));
$m1=mk($key,$topic,'{}',null,'1'); t('version 1 (sha1) ok',$v->verify($m1));
echo "$ok ok / $ko ko\n";
