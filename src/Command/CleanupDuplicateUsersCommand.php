<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande console « CleanupDuplicateUsersCommand ».
 */
class CleanupDuplicateUsersCommand extends Command
{
    protected static $defaultName = 'cleanup:duplicate-users';
    
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Nettoyer les utilisateurs en double par email')
            ->setHelp('Cette commande identifie et supprime les utilisateurs en double basés sur l\'email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Nettoyage des utilisateurs en double');
        
        // Trouver les emails en double
        $query = $this->entityManager->createQuery(
            'SELECT u.email, COUNT(u.id) as count 
             FROM App\Entity\User u 
             GROUP BY u.email 
             HAVING COUNT(u.id) > 1'
        );
        
        $duplicates = $query->getResult();
        
        if (empty($duplicates)) {
            $io->success('Aucun utilisateur en double trouvé.');
            return Command::SUCCESS;
        }
        
        $io->note(sprintf('Trouvé %d emails en double', count($duplicates)));
        
        $totalDeleted = 0;
        
        foreach ($duplicates as $duplicate) {
            $email = $duplicate['email'];
            $count = $duplicate['count'];
            
            $io->writeln(sprintf('  %s : %d occurrences', $email, $count));
            
            // Trouver tous les utilisateurs avec cet email
            $users = $this->entityManager->getRepository(User::class)
                ->findBy(['email' => $email], ['createdAt' => 'ASC']);
            
            // Garder le premier (le plus ancien) et supprimer les autres
            $firstUser = array_shift($users);
            
            foreach ($users as $userToDelete) {
                $this->entityManager->remove($userToDelete);
                $totalDeleted++;
            }
        }
        
        $this->entityManager->flush();
        
        $io->success(sprintf('Nettoyage terminé. %d utilisateurs en double supprimés.', $totalDeleted));
        
        return Command::SUCCESS;
    }
}