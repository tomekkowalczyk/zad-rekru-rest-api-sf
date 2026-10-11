<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Console;

use App\Security\Domain\User;
use App\Security\Infrastructure\Doctrine\Repository\DoctrineUserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Creates an API user able to obtain a JWT.')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly DoctrineUserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail used as the login')
            ->addArgument('password', InputArgument::REQUIRED, 'Plain password, hashed before storage')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant ROLE_ADMIN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getArgument('email');
        $password = (string) $input->getArgument('password');
        $roles = true === $input->getOption('admin') ? ['ROLE_ADMIN'] : [];

        if ($this->users->findOneByEmail($email) instanceof User) {
            $io->error(\sprintf('User "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $user = new User($email, $roles);
        $user->changePassword($this->passwordHasher->hashPassword($user, $password));
        $this->users->save($user);

        $io->success(\sprintf('User "%s" created with roles: %s.', $email, implode(', ', $user->getRoles())));

        return Command::SUCCESS;
    }
}
