<?php
// Import Senat sur fixtures. Lancer : APP_ENV=test php tests/e2e/e2e_senat_import.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use App\Entity\PartyProposal;
use App\Service\Programme\SenatImporter;
$k = new App\Kernel('test', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$em->createQuery("DELETE FROM App\Entity\PartyProposal p WHERE p.source = 'SENAT'")->execute();
$sen = "Matricule,Qualité,Nom usuel,Prénom usuel,État,Groupe politique\n1,M.,Dupont,Élie,ACTIF,Les Républicains\n2,Mme,de Cuttoli,Jeanne,ACTIF,SER\n3,M.,Ancien,Paul,ANCIEN,SER\n";
$ppl = "Session;Numéro de texte;Date de dépôt;Auteurs;Titre;Type de dossier;URL du dossier\n"
 . "2024;10;\"01/10/2024\";\"Élie DUPONT, Jeanne de CUTTOLI\";\"proposition de loi visant à renforcer les hôpitaux\";\"proposition de loi\";\"http://www.senat.fr/dossier-legislatif/ppl24-010.html\"\n"
 . "2024;11;\"02/10/2024\";\"Jeanne de CUTTOLI\";\"proposition de loi sur l'école\";\"proposition de loi\";\"http://www.senat.fr/dossier-legislatif/ppl24-011.html\"\n"
 . "2024;12;\"03/10/2024\";\"Paul ANCIEN\";\"proposition de loi d'un ancien sénateur\";\"proposition de loi\";\"http://www.senat.fr/dossier-legislatif/ppl24-012.html\"\n"
 . "2019;13;\"03/10/2019\";\"Élie DUPONT\";\"proposition de loi ancienne session\";\"proposition de loi\";\"http://www.senat.fr/dossier-legislatif/ppl19-013.html\"\n"
 . "2024;14;\"03/10/2024\";\"Élie DUPONT\";\"proposition de résolution quelconque\";\"proposition de résolution\";\"http://www.senat.fr/dossier-legislatif/ppr24-014.html\"\n";
$imp = new SenatImporter($em);
$s = $imp->import($ppl, $sen, 2023);
t('2 creees', $s['created'] === 2); t('1 ancien non importe (sans signataire en exercice)', $s['noGroup'] === 1); t('2 hors perimetre (session, type)', $s['skipped'] === 2);
$r = $em->getRepository(PartyProposal::class);
$a = $r->findOneBy(['externalId' => 'SEN-2024-10']); $b = $r->findOneBy(['externalId' => 'SEN-2024-11']);
t('groupe du PREMIER signataire (LR)', $a->getGroupCode() === 'SEN-LR' && str_contains($a->getGroupName(), '(Sénat)'));
t('accents et particules : de Cuttoli -> SER', $b->getGroupCode() === 'SEN-SER');
t('source https et origine Senat', str_starts_with($a->getSourceUrl(), 'https://www.senat.fr/') && $a->getSource() === 'SENAT');
t('date sans heure', $a->getDepositedAt()->format('Y-m-d H:i') === '2024-10-01 00:00');
$s2 = $imp->import($ppl, $sen, 2023); t('idempotent : 0 creee, 2 mises a jour', $s2['created'] === 0 && $s2['updated'] === 2);
$b = new Symfony\Bundle\FrameworkBundle\KernelBrowser($k); $b->disableReboot(); $b->request('GET', '/programmes?groupe=SEN-LR');
t('page programmes filtree par groupe Senat', str_contains($b->getResponse()->getContent(), 'renforcer les hôpitaux') && str_contains($b->getResponse()->getContent(), 'Sénat'));
$em->createQuery("DELETE FROM App\Entity\PartyProposal p WHERE p.source = 'SENAT'")->execute();
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
