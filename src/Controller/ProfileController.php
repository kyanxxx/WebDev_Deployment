<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_profile_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User not found');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $currentUser = $this->getUser();
        
        if (!$currentUser instanceof User) {
            throw $this->createAccessDeniedException('User not found');
        }

        // Refresh user entity from database to ensure it's managed
        $user = $entityManager->getRepository(User::class)->find($currentUser->getId());
        if (!$user) {
            throw $this->createAccessDeniedException('User not found');
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get form data directly from form fields
            $currentPassword = $form->get('currentPassword')->getData();
            $newPasswordData = $form->get('newPassword')->getData();
            
            // RepeatedType returns the value directly (the 'first' value when valid)
            $newPassword = is_array($newPasswordData) ? ($newPasswordData['first'] ?? null) : $newPasswordData;
            
            // Verify current password
            if (!$currentPassword || !$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Current password is incorrect.');
                return $this->render('profile/change_password.html.twig', [
                    'form' => $form,
                ]);
            }

            // Hash and set new password
            if (!$newPassword) {
                $this->addFlash('error', 'New password is required.');
                return $this->render('profile/change_password.html.twig', [
                    'form' => $form,
                ]);
            }

            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            
            // Explicitly persist to ensure entity is managed and changes are tracked
            $entityManager->persist($user);
            
            // Force Doctrine to detect the change by recomputing the change set
            $uow = $entityManager->getUnitOfWork();
            $uow->computeChangeSet(
                $entityManager->getClassMetadata(get_class($user)),
                $user
            );
            
            // Mark the entity as scheduled for update
            if (!$uow->isScheduledForUpdate($user)) {
                $uow->scheduleForUpdate($user);
            }
            
            $entityManager->flush();

            // Manually create activity log as fallback (in case subscriber doesn't fire)
            try {
                $currentUser = $this->getUser();
                if ($currentUser instanceof User) {
                    $activityLog = new ActivityLog();
                    $activityLog->setUser($currentUser);
                    $activityLog->setAction('UPDATE');
                    $activityLog->setEntityType('User');
                    $activityLog->setEntityId($user->getId());
                    
                    // Get user's primary role
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
                // Silently fail - don't break password change
            }

            $this->addFlash('success', '✅ Password changed successfully! Your new password has been saved.');
            return $this->redirectToRoute('app_profile_index');
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }
}

