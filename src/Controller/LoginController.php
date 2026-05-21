<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            // Redirect based on user role
            $user = $this->getUser();
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            } elseif ($this->isGranted('ROLE_STAFF')) {
                return $this->redirectToRoute('app_dashboard');
            } else {
                return $this->redirectToRoute('app_customer_index');
            }
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        
        // Check if user was disabled
        $disabled = $request->query->get('disabled');
        $errorMessage = null;
        
        if ($disabled) {
            $errorMessage = 'Your account has been disabled. Please contact an administrator.';
        } elseif ($request->query->getBoolean('oauth_error')) {
            $errorMessage = 'Google sign in failed. Please try again.';
        } elseif ($error) {
            // Change "Bad Credentials" to "Invalid Credentials"
            $errorMessage = $error->getMessage();
            if ($error instanceof BadCredentialsException || $errorMessage === 'Bad credentials.') {
                $errorMessage = 'Invalid Credentials';
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername, 
            'error' => $errorMessage
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
