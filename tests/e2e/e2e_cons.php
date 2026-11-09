<?php
require '/home/claude/app/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv('/home/claude/app/.env');
$k=new App\Kernel('test',true);$k->boot();$imp=$k->getContainer()->get('test.service_container')->get(App\Service\ActorImporter::class);
$f='/tmp/c.csv';file_put_contents($f,"email;organisation;source_url;consentement;preuve_consentement\nzz1@orga-test.fr;Orga;https://orga-test.fr/c;2026-10-01;Accord ecrit du 1er octobre\nzz2@orga-test.fr;Orga;https://orga-test.fr/c;2026-10-01;\n");
$r=$imp->import($f);echo json_encode($r),"\n";
