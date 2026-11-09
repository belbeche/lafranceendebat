<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
use App\Entity\{User,Discussion,DiscussionReply,Proposition};
function client($email=null){ $k=new App\Kernel('test',true); $k->boot(); $c=$k->getContainer()->get('test.client'); $c->disableReboot(); $c->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie("lfed_lang","fr"));
  if($email){ $u=$k->getContainer()->get('doctrine')->getRepository(User::class)->findOneBy(['email'=>$email]); $c->loginUser($u);} return $c; }
function em($c){ return $c->getContainer()->get('doctrine')->getManager(); }
$ok=0;$ko=0; function t($n,$c,$extra=''){global $ok,$ko; if($c){$ok++;echo "OK  $n\n";}else{$ko++;echo "KO  $n $extra\n";}}
// seed
$c=client(); $em=em($c);
foreach($em->getRepository(User::class)->findBy([]) as $u){ if(str_starts_with($u->getEmail(),'t_')) {$em->remove($u);} } $em->flush();
foreach($em->createQuery('DELETE FROM App\Entity\Gratitude')->execute()?[]:[] as $x){}
$em->createQuery('DELETE FROM App\Entity\DiscussionReply')->execute(); $em->createQuery('DELETE FROM App\Entity\Discussion')->execute();
function mk($em,$mail,$ageDays,$roles=[]){ $u=(new User())->setEmail($mail)->setFirstName(ucfirst(substr($mail,2,4)))->setLastName('Test')->setPassword('x')->setRoles($roles)->setIsActive(true); $u->setCreatedAt(new DateTimeImmutable("-$ageDays days")); $em->persist($u); return $u; }
mk($em,'t_old1@x.fr',30); mk($em,'t_old2@x.fr',30); mk($em,'t_old3@x.fr',30); mk($em,'t_new@x.fr',0); mk($em,'t_adm@x.fr',30,['ROLE_ADMIN']); $em->flush();

// anonyme
$c=client(); $c->request('GET','/communaute'); t('index public',$c->getResponse()->getStatusCode()===200);
$c->request('GET','/communaute/nouveau'); t('nouveau anonyme -> login',$c->getResponse()->isRedirect() && str_contains($c->getResponse()->headers->get('Location'),'/login'));
$c->request('POST','/communaute/1/repondre'); t('repondre anonyme -> login',$c->getResponse()->isRedirect());
$c->request('GET','/classement'); t('classement public',$c->getResponse()->getStatusCode()===200);

function submitThread($c,$title,$body){ $cr=$c->request('GET','/communaute/nouveau'); $f=$cr->selectButton('Publier le fil')->form(['discussion[title]'=>$title,'discussion[body]'=>$body,'discussion[category]'=>'dialogue','discussion[charter]'=>1]); return $c->submit($f); }
$long=' Voici un message construit, calme, qui explique le sujet et propose une piste concrete pour avancer ensemble.';
$c=client('t_old1@x.fr'); submitThread($c,'Comment discuter sans se diviser ?','Je voudrais'.$long); t('fil valide (compte ancien) publie',$c->getResponse()->isRedirect() && preg_match('~/communaute/\d+$~',$c->getResponse()->headers->get('Location')));
$d=em($c)->getRepository(Discussion::class)->findOneBy([]); t('statut valide',$d && $d->getStatus()===Proposition::STATUS_VALIDATED); $did=$d->getId();
$c=client('t_old1@x.fr'); $r=submitThread($c,'Prenons les armes ce soir','Il faut prendre les armes et renverser le gouvernement par la force, allons tous a l assaut des institutions.'.$long);
t('appel a la violence bloque (422)',$c->getResponse()->getStatusCode()===422 && str_contains($c->getResponse()->getContent(),'voie')) ;
t('...redirige vers voies institutionnelles',str_contains($c->getResponse()->getContent(),'Voies institutionnelles')||str_contains($c->getResponse()->getContent(),'institutionnelles'));
$c=client('t_old1@x.fr'); submitThread($c,'Un titre correct ici','Ce sont tous des connards et des abrutis'.$long); t('insulte bloquee',$c->getResponse()->getStatusCode()===422);
$c=client('t_old1@x.fr'); submitThread($c,'Un titre correct ici','Voir https://a.fr https://b.fr https://c.fr'.$long); t('3 liens bloques',$c->getResponse()->getStatusCode()===422);
$c=client('t_old1@x.fr'); submitThread($c,'Ecrivez moi ici svp','Mon numero est 06 12 34 56 78 appelez moi'.$long); t('donnee perso bloquee',$c->getResponse()->getStatusCode()===422);
$c=client('t_new@x.fr'); submitThread($c,'Premier fil de nouveau compte','Bonjour a tous'.$long); $n=em($c)->getRepository(Discussion::class)->findOneBy(['title'=>'Premier fil de nouveau compte']); t('nouveau compte -> en attente',$n && $n->getStatus()===Proposition::STATUS_PENDING);
$c=client(); $c->request('GET','/communaute/'.$n->getId()); t('fil en attente invisible (404)',$c->getResponse()->getStatusCode()===404);
// limite 3 fils / 24h
$c=client('t_old2@x.fr'); for($i=1;$i<=4;$i++){ submitThread($c,"Fil numero $i du jour","Message $i".$long); $c=client('t_old2@x.fr'); }
t('limite 3 fils/jour (4e refuse)', em($c)->getRepository(Discussion::class)->count(['author'=>em($c)->getRepository(User::class)->findOneBy(['email'=>'t_old2@x.fr'])])===3);

function reply($c,$id,$body,$ref=''){ $cr=$c->request('GET','/communaute/'.$id); $f=$cr->selectButton('Publier ma réponse')->form(['discussion_reply[body]'=>$body,'discussion_reply[reformulation]'=>$ref]); return $c->submit($f); }
$c=client('t_old2@x.fr'); reply($c,$did,'Merci pour ce fil, je pense que la meilleure voie est de commencer par ecouter.','Je comprends que vous voulez eviter la division.');
$rp=em($c)->getRepository(DiscussionReply::class)->findOneBy([]); t('reponse publiee',$rp && $rp->isVisible()); $rid=$rp->getId();
$c=client('t_new@x.fr'); reply($c,$did,'Je suis nouveau ici et je souhaite participer au dialogue calmement.'); $nr=em($c)->getRepository(DiscussionReply::class)->findOneBy(['author'=>em($c)->getRepository(User::class)->findOneBy(['email'=>'t_new@x.fr'])]); t('reponse nouveau compte en attente',$nr && !$nr->isVisible());
$c=client('t_old1@x.fr'); reply($c,$did,'Il faut tous les lyncher ces gens, vraiment, a la pendaison tout le monde.'); t('menace en reponse bloquee',$c->getResponse()->getStatusCode()===422);

// merci
function thank($c,$rid,$did){ $cr=$c->request('GET','/communaute/'.$did); $f=$cr->filter('form[action$="/reponse/'.$rid.'/merci"]'); if(!$f->count()) return false; $c->submit($f->selectButton($f->filter('button')->text())->form()); return true; }
$c=client('t_old2@x.fr'); t('pas de bouton merci sur sa propre reponse', !thank($c,$rid,$did));
$c=client('t_old1@x.fr'); thank($c,$rid,$did); $g=em($c)->getRepository(App\Entity\Gratitude::class)->count([]); t('merci enregistre',$g===1);
$c=client('t_old1@x.fr'); thank($c,$rid,$did); t('merci bascule (retire)', em($c)->getRepository(App\Entity\Gratitude::class)->count([])===0);
// CSRF
$c=client('t_old1@x.fr'); $c->request('POST','/communaute/reponse/'.$rid.'/merci',['_token'=>'bad']); t('merci sans CSRF refuse', em($c)->getRepository(App\Entity\Gratitude::class)->count([])===0);
// classement
$c=client('t_old1@x.fr'); thank($c,$rid,$did);
$c=client('t_old2@x.fr'); $c->request('GET','/classement?periode=tout'); t('classement vide tant que non opt-in', !str_contains($c->getResponse()->getContent(),'Semeur de dialogue'));
$cr=$c->request('GET','/classement'); $c->submit($cr->selectButton('Je veux apparaître dans le classement')->form());
$c=client('t_old2@x.fr'); $c->request('GET','/classement?periode=tout'); $h=$c->getResponse()->getContent(); t('apres opt-in : present avec niveau',str_contains($h,'Semeur de dialogue') && str_contains($h,'1 remerciement'));
t('score = 1 remerciement + 2 reformulation = 3', (bool)preg_match('~<span class="fw-bold">3</span>~',$h));
// donneur trop recent ne compte pas
$cn=client('t_new@x.fr'); $e=em($cn); $u=$e->getRepository(User::class)->findOneBy(['email'=>'t_new@x.fr']); $e->persist(new App\Entity\Gratitude($e->getRepository(DiscussionReply::class)->find($rid),$u)); $e->flush();
$c=client(); $c->request('GET','/classement?periode=tout'); t('donneur de moins de 7 jours ignore', (bool)preg_match('~<span class="fw-bold">3</span>~',$c->getResponse()->getContent()));
// signalement x3 retire le fil
$c=client('t_old1@x.fr'); $cr=$c->request('GET','/signaler/reponse/'.$rid); t('formulaire de signalement reponse',$c->getResponse()->getStatusCode()===200);
foreach(['t_old1@x.fr','t_old3@x.fr','t_adm@x.fr'] as $m){ $c=client($m); $cr=$c->request('GET','/signaler/reponse/'.$rid); $f=$cr->selectButton('Envoyer le signalement')->count()?$cr->selectButton('Envoyer le signalement')->form():$cr->filter('form button')->last()->form(); $f['signalement[reason]']='harcelement'; $c->submit($f);}
$c=client(); $c->request('GET','/communaute/'.$did); t('3 signalements : reponse retiree', !str_contains($c->getResponse()->getContent(),'Merci pour ce fil, je pense'));
// admin moderation
$c=client('t_adm@x.fr'); $c->request('GET','/admin/moderation'); $h=$c->getResponse()->getContent(); t('file de moderation liste fils/reponses en attente',$c->getResponse()->getStatusCode()===200 && str_contains($h,'Premier fil de nouveau compte'));
$c=client('t_old1@x.fr'); $c->request('GET','/admin/moderation'); t('moderation interdite a un utilisateur',$c->getResponse()->getStatusCode()===403);
echo "\n$ok ok, $ko ko\n";
