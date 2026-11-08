<?php

namespace App\Command;

use App\Entity\Lettre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Crée trois lettres modèles en BROUILLON (éducation et dialogue). Elles ne partent qu'après relecture et mise en file par un administrateur. */
#[AsCommand(name: 'app:lettre:modeles-education', description: 'Crée les lettres modèles (invitation acteurs publics, abonnés, acteurs consentants) en brouillon.')]
class SeedLettresEducationCommand extends Command
{
    private const MODELES = [
        [Lettre::AUD_INVITATION, 'Un débat national sur l\'éducation et les priorités du pays : votre avis compte',
"Madame, Monsieur,\n\nVous recevez ce message unique parce que votre adresse professionnelle est publiée dans un annuaire public au titre de vos fonctions. La France en Débat est une plateforme citoyenne gratuite et non partisane : elle ne soutient aucun parti ni syndicat.\n\nNous proposons d'ouvrir un débat calme et sourcé sur l'éducation, la transmission des savoirs et les priorités du pays, pour collaborer plutôt que s'opposer. Une pétition citoyenne en ce sens est en cours de collecte sur le site de l'Assemblée nationale : https://petitions.assemblee-nationale.fr/initiatives/i-7234\n\nVous pouvez répondre aux propositions des citoyens, donner un avis motivé et corriger les faits, dans les mêmes conditions que tout le monde : https://lafranceendebat.fr/dossiers/education\n\nCe message ne sera pas suivi de relance. Pour ne plus rien recevoir, un clic suffit sur le lien de désinscription ci-dessous."],
        [Lettre::AUD_SUBSCRIBERS, 'Le savoir se partage : le débat sur l\'éducation est ouvert',
"Bonjour,\n\nVous recevez cette lettre car vous vous êtes inscrit(e) et avez confirmé votre adresse.\n\nCe mois-ci : le débat « Faut-il apprendre à utiliser l'intelligence artificielle dès l'école ? » est ouvert. Chacun peut apporter un argument sourcé et répondre calmement à celui des autres : https://lafranceendebat.fr/debats/faut-il-apprendre-a-utiliser-l-intelligence-artificielle-des-l-ecole-2\n\nPour comprendre d'où vient notre école, de Guizot à Ferry, lisez le dossier : https://lafranceendebat.fr/dossiers/education\n\nLe savoir se partage, il ne se perd pas. À très vite pour en discuter.\n\nVous pouvez vous désinscrire à tout moment avec le lien ci-dessous."],
        [Lettre::AUD_ACTORS, 'Répondre aux citoyens : trois débats ouverts sur l\'éducation et la démocratie',
"Bonjour,\n\nVous recevez cette lettre car vous avez accepté de recevoir nos informations.\n\nTrois sujets attendent un avis motivé d'acteurs de terrain : l'IA à l'école, les priorités éducatives du pays, et le rôle de la participation citoyenne dans la Constitution. Chaque réponse publiée compte dans le classement des artisans de paix.\n\nDossier Constitution : https://lafranceendebat.fr/dossiers/constitution\nDossier Éducation : https://lafranceendebat.fr/dossiers/education\n\nUn clic sur le lien ci-dessous suffit pour vous désinscrire."],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $n = 0;
        foreach (self::MODELES as [$aud, $subject, $body]) {
            if ($this->em->getRepository(Lettre::class)->findOneBy(['subject' => $subject])) {
                continue;
            }
            $this->em->persist((new Lettre())->setSubject($subject)->setBody($body)->setAudience($aud));
            ++$n;
        }
        $this->em->flush();
        $output->writeln(sprintf('%d lettre(s) modèle(s) créée(s) en brouillon : Admin > Lettres. Relisez, envoyez-vous un test, puis mettez en file.', $n));

        return Command::SUCCESS;
    }
}
