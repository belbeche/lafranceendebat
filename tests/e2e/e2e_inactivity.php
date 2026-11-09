<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Proposition};
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Console\Input\ArrayInput;use Symfony\Component\Console\Output\BufferedOutput;
$ok=0;$ko=0;function t($n,$x){global $ok,$ko;if($x){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n\n";}}
function run($args){$k=new App\Kernel('test',true);$k->boot();$a=new Application($k);$a->setAutoExit(false);$o=new BufferedOutput();$a->run(new ArrayInput($args),$o);return $o->fetch();}
$k=new App\Kernel('test',true);$k->boot();$em=$k->getContainer()->get('doctrine')->getManager();
$mk=function($ago,$roles=['ROLE_USER'])use($em){$u=new User();$u->setEmail('in'.uniqid().'@t.fr')->setPassword('x')->setFirstName('I')->setLastName('N')->setRoles($roles)->setCreatedAt(new DateTimeImmutable("-$ago months"))->setLastLogin(new DateTimeImmutable("-$ago months"));$em->persist($u);$em->flush();return $u->getId();};
$old=$mk(30);$fresh=$mk(2);$adm=$mk(40,['ROLE_ADMIN']);$old2=$mk(30);$old3=$mk(30);
$o=run(['command'=>'app:comptes:inactifs','--simuler'=>true]);t('simulation: annonce sans écrire',str_contains($o,'[simulation]'));
$em->clear();t('simulation: aucun avis posé',$em->getRepository(User::class)->find($old)->getInactivityNoticeAt()===null);
$o=run(['command'=>'app:comptes:inactifs']);
$em->clear();
$U=fn($id)=>$em->getRepository(User::class)->find($id);
t('inactif: avis posé',$U($old)->getInactivityNoticeAt()!==null);
t('récent: pas d\'avis',$U($fresh)->getInactivityNoticeAt()===null);
t('admin exclu',$U($adm)->getInactivityNoticeAt()===null);
// lien signé
$signer=$k->getContainer()->get('test.service_container')->get(Symfony\Component\HttpFoundation\UriSigner::class);
$url=$signer->sign('http://localhost/compte/inactivite/'.$old.'?exp='.(time()+99999));
$b=new KernelBrowser($k);$b->request('GET',$url);t('lien signé: page de choix',$b->getResponse()->getStatusCode()==200&&str_contains($b->getResponse()->getContent(),'Garder mon compte'));
$b->request('GET','/compte/inactivite/'.$old.'?exp=1&_hash=xx');t('lien falsifié refusé',$b->getResponse()->getStatusCode()==403);
t('ouvrir le lien ne supprime rien',$U($old)!==null);
$cr=$b->request('GET',$url);$tok=$cr->filter('main input[name=_token]')->attr('value');
$b->request('POST',$url,['_token'=>$tok,'choice'=>'keep']);$em->clear();
t('garder: avis levé et compteur remis à zéro',$U($old)->getInactivityNoticeAt()===null&&$U($old)->getLastLogin()>new DateTimeImmutable('-1 day'));
// suppression volontaire
$url2=$signer->sign('http://localhost/compte/inactivite/'.$old2.'?exp='.(time()+99999));
$cr=$b->request('GET',$url2);$tok=$cr->filter('main input[name=_token]')->attr('value');
$b->request('POST',$url2,['_token'=>$tok,'choice'=>'delete']);$em->clear();
t('supprimer: compte effacé',$U($old2)===null);
// délai dépassé -> suppression automatique
$em->getConnection()->executeStatement('UPDATE users SET inactivity_notice_at = ? WHERE id = ?',[(new DateTimeImmutable('-31 days'))->format('Y-m-d H:i:s'),$old3]);
$o=run(['command'=>'app:comptes:inactifs']);$em->clear();
t('sans réponse après 30 jours: supprimé',$U($old3)===null);
t('récent et admin toujours là',$U($fresh)!==null&&$U($adm)!==null);
// retour de l'utilisateur annule l'avis
$back=$mk(30);$em->getConnection()->executeStatement('UPDATE users SET inactivity_notice_at = ?, last_login = ? WHERE id = ?',[(new DateTimeImmutable('-5 days'))->format('Y-m-d H:i:s'),(new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),$back]);
$o=run(['command'=>'app:comptes:inactifs']);$em->clear();t('reconnecté: avis annulé',$U($back)->getInactivityNoticeAt()===null);
echo "\n$ok OK / $ko KO\n";
