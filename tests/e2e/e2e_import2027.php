<?php
// Import des programmes 2027. Lancer : APP_ENV=test php tests/e2e/e2e_import2027.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Command\ProgrammesImport2027Command as C;
use App\Entity\PartyProposal;
use Symfony\Component\Console\Tester\CommandTester;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
t('édition 2022 refusée', null !== C::problem(['edition' => '2022', 'group_code' => 'AB', 'group_name' => 'Abc', 'source_url' => 'https://parti.fr/p', 'measures' => [1]]));
t('source tierce refusée', null !== C::hostProblem('https://monvote2027.fr/candidat/x'));
t('http refusé', null !== C::hostProblem('http://parti.fr/p'));
t('site officiel accepté', null === C::hostProblem('https://parti.fr/p'));
$g = 'T' . strtoupper(substr(uniqid(), -5));
$file = sys_get_temp_dir() . '/p2027.json';
file_put_contents($file, json_encode(['edition' => '2027', 'group_code' => $g, 'group_name' => 'Formation test', 'source_url' => 'https://parti-test.fr/programme', 'measures' => [['title' => 'Mesure de test numéro un', 'summary' => 'Résumé.', 'theme' => 'Santé'], ['title' => 'Court', 'summary' => 'x']]]));
$cmd = new CommandTester(new C($em));
$cmd->execute(['fichier' => $file]);
$n = $em->getRepository(PartyProposal::class)->count(['groupCode' => $g]);
t('une mesure importée, la trop courte ignorée', $n === 1);
$p = $em->getRepository(PartyProposal::class)->findOneBy(['groupCode' => $g]);
t('importée masquée', $p && !$p->isPublished());
$cmd->execute(['fichier' => $file]); $em->clear();
t('pas de doublon au second passage', $em->getRepository(PartyProposal::class)->count(['groupCode' => $g]) === 1);
$em->createQuery('DELETE FROM App\Entity\PartyProposal p WHERE p.groupCode = :g')->setParameter('g', $g)->execute();
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
