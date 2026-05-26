<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class GoogleAccountService
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function findOrCreateCustomerFromEmail(string $email): User
    {
        $email = trim($email);
        if ($email === '') {
            throw new \InvalidArgumentException('Google account did not return an email.');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            return $user;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($this->generateUniqueUsername($email));
        $user->setRoles([]);
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setStatus('active');
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function generateUniqueUsername(string $email): string
    {
        $base = preg_replace('/[^a-zA-Z0-9_]/', '_', strstr($email, '@', true) ?: $email);
        $base = trim($base ?? '', '_');
        $base = $base !== '' ? $base : 'google_user';

        $candidate = $base;
        $counter = 1;

        while ($this->userRepository->findOneBy(['username' => $candidate]) instanceof User) {
            $candidate = sprintf('%s_%d', $base, $counter);
            ++$counter;
        }

        return $candidate;
    }
}
