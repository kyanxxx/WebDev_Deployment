<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash password if provided
            if ($form->has('plainPassword')) {
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }
            }

            // Roles are already converted to array by the form transformer
            $entityManager->persist($user);
            $entityManager->flush();

            // Manually create activity log as fallback (Admin creates user)
            try {
                $currentUser = $this->getUser();
                if ($currentUser instanceof User) {
                    $activityLog = new ActivityLog();
                    $activityLog->setUser($currentUser);
                    $activityLog->setAction('CREATE');
                    $activityLog->setEntityType('User');
                    $activityLog->setEntityId($user->getId());
                    
                    $roles = $currentUser->getRoles();
                    $userRole = 'ROLE_USER';
                    if (in_array('ROLE_ADMIN', $roles, true)) {
                        $userRole = 'ROLE_ADMIN';
                    } elseif (in_array('ROLE_STAFF', $roles, true)) {
                        $userRole = 'ROLE_STAFF';
                    }
                    $activityLog->setUserRole($userRole);
                    
                    $entityManager->persist($activityLog);
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                // Silently fail
            }

            $this->addFlash('success', 'User created successfully!');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true,
            'user' => $user,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password reset if provided
            if ($form->has('plainPassword')) {
                $plainPassword = $form->get('plainPassword')->getData();
                if ($plainPassword) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                }
            }

            // Roles are already converted to array by the form transformer
            // Ensure entity is persisted and changes are tracked
            $entityManager->persist($user);
            
            // Force Doctrine to detect the change
            $uow = $entityManager->getUnitOfWork();
            $uow->computeChangeSet(
                $entityManager->getClassMetadata(get_class($user)),
                $user
            );
            
            if (!$uow->isScheduledForUpdate($user)) {
                $uow->scheduleForUpdate($user);
            }
            
            $entityManager->flush();

            // Manually create activity log as fallback (Admin updates user)
            try {
                $currentUser = $this->getUser();
                if ($currentUser instanceof User) {
                    $activityLog = new ActivityLog();
                    $activityLog->setUser($currentUser);
                    $activityLog->setAction('UPDATE');
                    $activityLog->setEntityType('User');
                    $activityLog->setEntityId($user->getId());
                    
                    $roles = $currentUser->getRoles();
                    $userRole = 'ROLE_USER';
                    if (in_array('ROLE_ADMIN', $roles, true)) {
                        $userRole = 'ROLE_ADMIN';
                    } elseif (in_array('ROLE_STAFF', $roles, true)) {
                        $userRole = 'ROLE_STAFF';
                    }
                    $activityLog->setUserRole($userRole);
                    
                    $entityManager->persist($activityLog);
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                // Silently fail
            }

            $this->addFlash('success', 'User updated successfully!');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/reset-password', name: 'app_user_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('new_password');
            
            if (!$newPassword || strlen($newPassword) < 6) {
                $this->addFlash('error', 'Password must be at least 6 characters long.');
                return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            
            // Ensure entity is persisted and changes are tracked
            $entityManager->persist($user);
            
            // Force Doctrine to detect the change
            $uow = $entityManager->getUnitOfWork();
            $uow->computeChangeSet(
                $entityManager->getClassMetadata(get_class($user)),
                $user
            );
            
            if (!$uow->isScheduledForUpdate($user)) {
                $uow->scheduleForUpdate($user);
            }
            
            $entityManager->flush();

            // Manually create activity log as fallback
            try {
                $currentUser = $this->getUser();
                if ($currentUser instanceof User) {
                    $activityLog = new ActivityLog();
                    $activityLog->setUser($currentUser);
                    $activityLog->setAction('UPDATE');
                    $activityLog->setEntityType('User');
                    $activityLog->setEntityId($user->getId());
                    
                    $roles = $currentUser->getRoles();
                    $userRole = 'ROLE_USER';
                    if (in_array('ROLE_ADMIN', $roles, true)) {
                        $userRole = 'ROLE_ADMIN';
                    } elseif (in_array('ROLE_STAFF', $roles, true)) {
                        $userRole = 'ROLE_STAFF';
                    }
                    $activityLog->setUserRole($userRole);
                    
                    $entityManager->persist($activityLog);
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                // Silently fail
            }

            $this->addFlash('success', 'Password reset successfully!');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/reset_password.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST', 'DELETE'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        // Prevent deleting yourself
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('app_user_index');
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $userId = $user->getId(); // Store ID before deletion
            
            $entityManager->remove($user);
            $entityManager->flush();

            // Manually create activity log as fallback (Admin deletes user)
            try {
                $currentUser = $this->getUser();
                if ($currentUser instanceof User) {
                    $activityLog = new ActivityLog();
                    $activityLog->setUser($currentUser);
                    $activityLog->setAction('DELETE');
                    $activityLog->setEntityType('User');
                    $activityLog->setEntityId($userId);
                    
                    $roles = $currentUser->getRoles();
                    $userRole = 'ROLE_USER';
                    if (in_array('ROLE_ADMIN', $roles, true)) {
                        $userRole = 'ROLE_ADMIN';
                    } elseif (in_array('ROLE_STAFF', $roles, true)) {
                        $userRole = 'ROLE_STAFF';
                    }
                    $activityLog->setUserRole($userRole);
                    
                    $entityManager->persist($activityLog);
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                // Silently fail
            }

            $this->addFlash('success', 'User deleted successfully!');
        }

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/toggle-status', name: 'app_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        // Prevent disabling yourself
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot disable your own account.');
            return $this->redirectToRoute('app_user_index');
        }

        if ($this->isCsrfTokenValid('toggle_status'.$user->getId(), $request->request->get('_token'))) {
            $newStatus = $request->request->get('status', 'disabled');
            $user->setStatus($newStatus);
            $entityManager->flush();

            $statusMessage = ucfirst($newStatus);
            $this->addFlash('success', "User status changed to {$statusMessage}!");
        }

        return $this->redirectToRoute('app_user_index');
    }
}

