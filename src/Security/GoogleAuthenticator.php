<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use KnpU\OAuth2ClientBundle\Client\Provider\GoogleClient;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        /** @var GoogleClient $client */
        $client = $this->clientRegistry->getClient('google_main');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): User {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);
                $email = $googleUser->getEmail();

                if (!$email) {
                    throw new AuthenticationException('Google account did not return an email.');
                }

                $user = $this->userRepository->findOneBy(['email' => $email]);
                if ($user instanceof User) {
                    return $user;
                }

                $user = new User();
                $user->setEmail($email);
                $user->setUsername($this->generateUniqueUsername($email));
                $user->setRoles(['ROLE_USER']);
                $user->setIsVerified(true);
                $user->setVerificationToken(null);
                $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if ($user instanceof User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        if ($user instanceof User && in_array('ROLE_STAFF', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_customer_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login', [
            'oauth_error' => 1,
        ]));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
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
