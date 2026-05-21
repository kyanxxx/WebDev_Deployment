<?php

namespace App\Controller;

use App\Entity\Products;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class OrderFormController extends AbstractController
{
    #[Route('/admin/orders', name: 'app_order_form')]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"))]
    public function index(EntityManagerInterface $em): Response
    {
        // Fetch all products and their related orders
        $products = $em->getRepository(Products::class)->findAll();

        return $this->render('order_form/index.html.twig', [
            'products' => $products,
        ]);
    }
}
