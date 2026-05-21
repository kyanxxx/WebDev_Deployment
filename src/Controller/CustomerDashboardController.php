<?php

namespace App\Controller;

use App\Repository\ProductsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CustomerDashboardController extends AbstractController
{
    #[Route('/customer/dashboard', name: 'app_customer_index')]
    #[IsGranted('ROLE_USER')]
    public function index(ProductsRepository $productsRepository): Response
    {
        return $this->render('customer_dashboard/index.html.twig', [
            'products' => $productsRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/customer/menu', name: 'app_customer_menu')]
    #[IsGranted('ROLE_USER')]
    public function menu(): Response
    {
        $menuSections = [
            [
                'title' => 'Our Best Sellers',
                'items' => [
                    ['name' => 'Iced Latte', 'price' => 4.50, 'image' => 'iced-latte.jpg', 'description' => 'Cool and creamy espresso with milk over ice.'],
                    ['name' => 'Caramel Macchiato', 'price' => 5.20, 'image' => 'caramel-macchiato.jpg', 'description' => 'Rich espresso layered with vanilla and caramel.'],
                    ['name' => 'Mocha', 'price' => 4.80, 'image' => 'mocha.jpg', 'description' => 'Espresso with chocolate and steamed milk.'],
                    ['name' => 'Cappuccino', 'price' => 4.00, 'image' => 'cappuccino.jpg', 'description' => 'Espresso topped with steamed milk foam.'],
                    ['name' => 'Americano', 'price' => 3.80, 'image' => 'Americano.jpg', 'description' => 'Smooth espresso diluted with hot water.'],
                    ['name' => 'Frappuccino', 'price' => 5.50, 'image' => 'frappuccino.jpg', 'description' => 'Blended iced coffee with sweet whipped cream.'],
                    ['name' => 'Vanilla Latte', 'price' => 4.70, 'image' => 'vanilla-latte.jpg', 'description' => 'Espresso mixed with steamed milk and vanilla syrup.'],
                    ['name' => 'Hazelnut Cold Brew', 'price' => 5.10, 'image' => 'hazelnut-coldbrew.jpg', 'description' => 'Chilled slow-brew coffee with hazelnut flavor.'],
                ],
            ],
            [
                'title' => 'Tea & Non-Coffee',
                'items' => [
                    ['name' => 'Thai Tea', 'price' => 4.50, 'image' => 'Thai-Iced-Tea.jpg', 'description' => 'Black tea sweetened with condensed milk—iced or hot.'],
                    ['name' => 'Chai Tea', 'price' => 4.50, 'image' => 'chai-tea.jpg', 'description' => 'Warm spices steeped with black tea and steamed milk.'],
                    ['name' => 'Matcha Latte', 'price' => 5.50, 'image' => 'Matcha-Latte.jpg', 'description' => 'Stone-ground green tea whisked with steamed milk.'],
                    ['name' => 'Hot Chocolate', 'price' => 4.50, 'image' => 'hot-chocolate-coffee.jpg', 'description' => 'Rich cocoa with steamed milk and a cloud of whipped cream.'],
                    ['name' => 'Herbal Tea', 'price' => 3.50, 'image' => 'herbal-tea.jpg', 'description' => 'Caffeine-free botanical blend—ask for today’s selection.'],
                ],
            ],
            [
                'title' => 'Pastries & Snacks',
                'items' => [
                    ['name' => 'Croissant', 'price' => 3.50, 'image' => 'Croissant.jpg', 'description' => 'Buttery, flaky layers baked fresh for the morning rush.'],
                    ['name' => 'Blueberry Muffin', 'price' => 3.00, 'image' => 'blueberry-muffins.jpg', 'description' => 'Moist muffin studded with wild blueberries.'],
                    ['name' => 'Chocolate Cookie', 'price' => 2.50, 'image' => 'Chocolate-Cookies.jpg', 'description' => 'Soft-baked cookie with melty dark chocolate chunks.'],
                    ['name' => 'Avocado Toast', 'price' => 6.50, 'image' => 'Avocado Toast.jpg', 'description' => 'Smashed avocado on artisan toast with sea salt and citrus.'],
                ],
            ],
        ];

        return $this->render('customer_dashboard/menu.html.twig', [
            'menuSections' => $menuSections,
        ]);
    }

    #[Route('/customer/products/options', name: 'app_customer_product_options', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function productOptions(ProductsRepository $productsRepository): JsonResponse
    {
        $products = $productsRepository->findBy([], ['name' => 'ASC']);
        $items = array_map(static fn($product) => [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
        ], $products);

        return $this->json([
            'products' => $items,
        ]);
    }
}
