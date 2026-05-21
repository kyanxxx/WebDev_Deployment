<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\EventSubscriber;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\Persistence\ManagerRegistry;

class ActivityLogSubscriber implements EventSubscriber
{
    private ?User $currentUser = null;
    private array $pendingLogs = [];

    public function __construct(
        private ManagerRegistry $registry,
        private Security $security
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
            Events::postFlush,
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Don't log ActivityLog itself to avoid infinite loops
        if ($entity instanceof ActivityLog) {
            return;
        }

        // Only log entities we care about
        if (!$this->shouldLogEntity($entity)) {
            return;
        }

        $this->logAction('CREATE', $entity);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Don't log ActivityLog itself
        if ($entity instanceof ActivityLog) {
            return;
        }

        // Only log entities we care about
        if (!$this->shouldLogEntity($entity)) {
            return;
        }

        $this->logAction('UPDATE', $entity);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Don't log ActivityLog itself
        if ($entity instanceof ActivityLog) {
            return;
        }

        // Only log entities we care about
        if (!$this->shouldLogEntity($entity)) {
            return;
        }

        $this->logAction('DELETE', $entity);
    }

    private function shouldLogEntity(object $entity): bool
    {
        // Only log specific entities (Products, Orders, User)
        $loggableEntities = [
            'App\Entity\Products',
            'App\Entity\Orders',
            'App\Entity\User',
        ];

        return in_array(get_class($entity), $loggableEntities, true);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // Process pending logs after the main flush completes
        if (empty($this->pendingLogs)) {
            return;
        }

        $em = $args->getObjectManager();
        $logsToSave = $this->pendingLogs;
        $this->pendingLogs = []; // Clear immediately to avoid recursion
        
        foreach ($logsToSave as $logData) {
            try {
                $activityLog = new ActivityLog();
                $activityLog->setUser($logData['user']);
                $activityLog->setAction($logData['action']);
                $activityLog->setEntityType($logData['entityType']);
                $activityLog->setEntityId($logData['entityId']);
                $activityLog->setUserRole($logData['userRole']);
                
                $em->persist($activityLog);
            } catch (\Exception $e) {
                // Log error but don't break
                error_log('ActivityLog error: ' . $e->getMessage());
            }
        }
        
        // Flush the activity logs
        if (!empty($logsToSave)) {
            try {
                $em->flush();
            } catch (\Exception $e) {
                error_log('ActivityLog flush error: ' . $e->getMessage());
            }
        }
    }

    private function logAction(string $action, object $entity): void
    {
        // Get current user
        $user = $this->getCurrentUser();
        
        // Skip logging if no user (e.g., CLI commands, fixtures)
        if (!$user) {
            return;
        }

        // Get entity type (short class name)
        $entityType = $this->getEntityType($entity);
        
        // Get entity ID
        $entityId = $this->getEntityId($entity);
        
        // Get user's primary role
        $userRole = $this->getUserRole($user);

        // Store log data to be processed after flush
        $this->pendingLogs[] = [
            'user' => $user,
            'action' => $action,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'userRole' => $userRole,
        ];
    }

    private function getCurrentUser(): ?User
    {
        if ($this->currentUser === null) {
            $token = $this->security->getToken();
            if ($token && $token->getUser() instanceof User) {
                $this->currentUser = $token->getUser();
            }
        }

        return $this->currentUser;
    }

    private function getEntityType(object $entity): string
    {
        $className = get_class($entity);
        $parts = explode('\\', $className);
        return end($parts);
    }

    private function getEntityId(object $entity): int
    {
        // Try to get ID using reflection or method call
        if (method_exists($entity, 'getId')) {
            return $entity->getId() ?? 0;
        }

        return 0;
    }

    private function getUserRole(User $user): string
    {
        $roles = $user->getRoles();
        
        // Return the highest role (Admin > Staff > User)
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return 'ROLE_ADMIN';
        }
        
        if (in_array('ROLE_STAFF', $roles, true)) {
            return 'ROLE_STAFF';
        }
        
        return 'ROLE_USER';
    }
}

