<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create admin and staff users with proper roles',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create Admin User
        $admin = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'admin']);
        if (!$admin) {
            $admin = new User();
            $admin->setUsername('admin');
            $this->configureStaffAccount($admin, ['ROLE_ADMIN']);
            $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
            $admin->setPassword($hashedPassword);
            $this->entityManager->persist($admin);
            $io->success('Admin user created: username=admin, password=admin123');
        } else {
            $this->configureStaffAccount($admin, ['ROLE_ADMIN']);
            $io->info('Admin user already exists, updated roles to ROLE_ADMIN');
        }

        // Create Staff User
        $staff = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'staff']);
        if (!$staff) {
            $staff = new User();
            $staff->setUsername('staff');
            $this->configureStaffAccount($staff, ['ROLE_STAFF']);
            $hashedPassword = $this->passwordHasher->hashPassword($staff, 'staff123');
            $staff->setPassword($hashedPassword);
            $this->entityManager->persist($staff);
            $io->success('Staff user created: username=staff, password=staff123');
        } else {
            $this->configureStaffAccount($staff, ['ROLE_STAFF']);
            $io->info('Staff user already exists, updated roles to ROLE_STAFF');
        }

        // Create Regular User (for testing)
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'user']);
        if (!$user) {
            $user = new User();
            $user->setUsername('user');
            $user->setRoles([]); // Will get ROLE_USER automatically from getRoles()
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'user123');
            $user->setPassword($hashedPassword);
            $this->entityManager->persist($user);
            $io->success('Regular user created: username=user, password=user123');
        } else {
            $io->info('Regular user already exists');
        }

        $this->entityManager->flush();

        $io->note([
            'Users created/updated:',
            'Admin: username=admin, password=admin123, role=ROLE_ADMIN',
            'Staff: username=staff, password=staff123, role=ROLE_STAFF',
            'User: username=user, password=user123, role=ROLE_USER',
            '',
            '⚠️  Please change these passwords in production!'
        ]);

        return Command::SUCCESS;
    }

    private function configureStaffAccount(User $user, array $roles): void
    {
        $user->setRoles($roles);
        $user->setStatus('active');
        $user->setIsVerified(true);
        $user->setVerificationToken(null);

        if ($user->getCreatedAt() === null) {
            $user->setCreatedAt(new \DateTime());
        }
    }
}

