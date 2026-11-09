<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{Revendication,ContentTranslation};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.service_container');
$t=$c->get(App\Service\ContentTranslator::class);$em=$c->get('doctrine')->getManager();
echo 'provider=',$t->provider(),"\n";
$r=$em->getRepository(Revendication::class)->findOneBy([]);
if(!$r){echo "pas de revendication\n";exit;}
$loc='en'; if($r->getLocale()==='en')$loc='es';
$em->getRepository(ContentTranslation::class)->createQueryBuilder('x')->delete()->getQuery()->execute();
try{$ok=$t->autoTranslate($r,$loc);echo 'ok=',var_export($ok,true),"\n";$tr=$t->translation($r,$loc);echo json_encode($tr?->getFields(),JSON_UNESCAPED_UNICODE),"\n";}catch(Throwable $e){echo get_class($e),' ',$e->getCode(),' ',$e->getMessage(),"\n";}
