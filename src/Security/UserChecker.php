<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->isDisabled()) {
            throw new CustomUserMessageAccountStatusException('Your account has been disabled. Please contact an administrator.');
        }

        if ($user->isArchived()) {
            throw new CustomUserMessageAccountStatusException('Your account has been archived. Please contact an administrator.');
        }

        // Only block users who are still in an active email-verification flow.
        // Legacy users created before this feature may have isVerified=false but no token.
        if (!$user->isVerified() && $user->getVerificationToken() !== null) {
            throw new CustomUserMessageAccountStatusException('Please verify your email address before logging in.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No additional checks needed after authentication
    }
}

