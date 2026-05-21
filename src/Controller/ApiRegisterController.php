<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiRegisterController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        ValidatorInterface $validator,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }

        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? $data['plainPassword'] ?? '');
        $email = isset($data['email']) ? trim((string) $data['email']) : null;
        if ($email === '') {
            $email = null;
        }

        $constraints = new Assert\Collection([
            'username' => [
                new Assert\NotBlank(message: 'Please choose a username'),
                new Assert\Length(min: 3, max: 180, minMessage: 'Username must be at least {{ limit }} characters'),
            ],
            'password' => [
                new Assert\NotBlank(message: 'Please enter a password'),
                new Assert\Length(min: 6, max: 4096, minMessage: 'Password must be at least {{ limit }} characters'),
            ],
            'email' => new Assert\Optional([
                new Assert\Email(message: 'Please enter a valid email address'),
            ]),
        ]);

        $violations = $validator->validate(
            ['username' => $username, 'password' => $password, 'email' => $email],
            $constraints,
        );

        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $property = trim($violation->getPropertyPath(), '[]');
                $errors[$property] = $violation->getMessage();
            }

            return $this->json(
                ['message' => 'Validation failed', 'errors' => $errors],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($userRepository->findOneBy(['username' => $username]) !== null) {
            return $this->json(
                ['message' => 'There is already an account with this username'],
                Response::HTTP_CONFLICT,
            );
        }

        if ($email !== null && $userRepository->findOneBy(['email' => $email]) !== null) {
            return $this->json(
                ['message' => 'There is already an account with this email'],
                Response::HTTP_CONFLICT,
            );
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles([]);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setStatus('active');
        $user->setIsVerified(true);
        $user->setVerificationToken(null);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(
            [
                'message' => 'Account created',
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
            ],
            Response::HTTP_CREATED,
        );
    }
}
