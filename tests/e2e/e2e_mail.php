<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Contact,Proposition,RoleRequest};
$k=new App\Kernel('test',true);$k->boot();$c=$k->getContainer()->get('test.client');$c->disableReboot();
$ok=0;$ko=0; function t($n,$x){global $ok,$ko; if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
$c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang","fr"));
$em=$c->getContainer()->get('doctrine')->getManager();
$m=$c->getContainer()->get(App\Service\TransactionalMailer::class);
$u=$em->getRepository(User::class)->findOneBy([]);
// envoi direct via service public ?
$ref=new ReflectionClass($m);
$u->setEmail('dest@x.fr');
$m->welcome($u);
$ct=(new Contact())->setNom('Dupont')->setPrenom('Jean')->setEmail('jean@x.fr')->setSujet('Question')->setMessage("Bonjour\nBcc: evil@x.fr");
$m->contactReceived($ct);
$p=new Proposition();$p->setTitle("Ma proposition");(new ReflectionProperty($p,"id"))->setValue($p,7);$p->setAuthor($u);$m->contentDecision($p,false,'Manque de sources');
$rr=new RoleRequest($u,'ROLE_MODERATOR','texte'); $m->roleDecision($rr);
$msgs=$c->getContainer()->get('mailer.message_logger_listener')->getEvents()->getMessages();
t('4+ mails envoyes (sync)',count($msgs)>=5);
foreach($msgs as $x){ echo '  ',$x->getSubject(),' -> ',implode(',',array_map(fn($a)=>$a->getAddress(),$x->getTo())),"\n"; }
$html=array_map(fn($x)=>$x->getHtmlBody(),$msgs);
t('motif present',(bool)array_filter($html,fn($h)=>str_contains($h,'Manque de sources')));
t('pas d injection d en-tete',!array_filter($msgs,fn($x)=>$x->getHeaders()->has('Bcc')));
echo "$ok ok / $ko ko\n";
