<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{Revendication,ContentTranslation};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();
$em=$c->getContainer()->get('doctrine')->getManager();
function ok($n,$x){echo ($x?'OK  ':'KO  ').$n."\n";}
$r=$em->getRepository(Revendication::class)->findOneBy(['slug'=>'pm-transparence-resultats']);
$em->getConnection()->executeStatement("DELETE FROM content_translation WHERE type='revendication' AND ref_id=? AND locale='it'",[$r->getId()]);
$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie('lfed_lang','it'));
$c->request('GET','/revendications/'.$r->getSlug());ok('page 200',$c->getResponse()->getStatusCode()==200);
$n=$em->getConnection()->fetchOne("SELECT COUNT(*) FROM content_translation WHERE type='revendication' AND ref_id=? AND locale='it'",[$r->getId()]);
ok('traduction creee apres la reponse',(int)$n===1);
$c->request('GET','/fil');ok('fil 200',$c->getResponse()->getStatusCode()==200);
echo substr_count($c->getResponse()->getContent(),'feed-card')," cartes\n";
