<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class SecurityEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $this->logSecurityAction('LOGIN', $user);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        $this->logSecurityAction('LOGOUT', $user);
    }

    private function logSecurityAction(string $action, User $user): void
    {
        try {
            $activityLog = new ActivityLog();
            $activityLog->setUser($user);
            $activityLog->setAction($action);
            $activityLog->setEntityType('User');
            $activityLog->setEntityId($user->getId());
            
            // Get user's primary role
            $roles = $user->getRoles();
            $userRole = 'ROLE_USER';
            if (in_array('ROLE_ADMIN', $roles, true)) {
                $userRole = 'ROLE_ADMIN';
            } elseif (in_array('ROLE_STAFF', $roles, true)) {
                $userRole = 'ROLE_STAFF';
            }
            $activityLog->setUserRole($userRole);
            
            $this->entityManager->persist($activityLog);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Silently fail to avoid breaking login/logout
            error_log('SecurityEventSubscriber error: ' . $e->getMessage());
        }
    }
}

