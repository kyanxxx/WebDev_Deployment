<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserStatusSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UserRepository $userRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        
        if (!$token || !$token->getUser() instanceof User) {
            return;
        }

        $user = $token->getUser();
        
        // Skip check for login and logout pages
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (in_array($route, ['app_login', 'app_logout'], true)) {
            return;
        }

        // Refresh user from database to get latest status
        $freshUser = $this->userRepository->find($user->getId());
        
        if (!$freshUser) {
            // User no longer exists, log them out
            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
            return;
        }

        // Check if user is disabled or archived
        if ($freshUser->isDisabled() || $freshUser->isArchived()) {
            // Log out the user
            $this->tokenStorage->setToken(null);
            $request->getSession()->invalidate();
            
            // Redirect to login with message
            $event->setResponse(new RedirectResponse(
                $this->urlGenerator->generate('app_login', [
                    'disabled' => '1'
                ])
            ));
        }
    }
}

