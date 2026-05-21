<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LandingPageController extends AbstractController
{
    #[Route('/', name: 'app_landing_page')]
    public function index(): Response
    {
        // Redirect logged-in users away from landing page
        if ($this->getUser()) {
            // Redirect based on user role
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            } elseif ($this->isGranted('ROLE_STAFF')) {
                return $this->redirectToRoute('app_dashboard');
            } else {
                return $this->redirectToRoute('app_customer_index');
            }
        }

        return $this->render('landing_page/index.html.twig', [
            'controller_name' => 'LandingPageController',
        ]);
    }
}
