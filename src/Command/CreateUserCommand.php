<?php
// src/Command/CreateUserCommand.php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Commande console "CreateUserCommand".
 *
 * Lancer : php bin/console app:create-user. Creates a new user
 */
#[AsCommand(name: 'app:create-user', description: 'Creates a new user')]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('password', InputArgument::REQUIRED, 'User password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = new User();
        $user->setEmail($input->getArgument('email'));
        $user->setRoles(['ROLE_USER']);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $input->getArgument('password'));
        $user->setPassword($hashedPassword);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        $output->writeln('User created successfully!');
        
        return Command::SUCCESS;
    }
}