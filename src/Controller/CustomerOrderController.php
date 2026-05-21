<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Orders;
use App\Entity\Products;
use App\Entity\User;
use App\Repository\ProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CustomerOrderController extends AbstractController
{
    #[Route('/customer/orders/place', name: 'app_customer_orders_place', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function placeCustomerOrder(
        Request $request,
        ProductsRepository $productsRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('This order form is for customers only.');
        }

        if (!$this->isCsrfTokenValid('customer-order-place', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid order request. Please try again.');
            return $this->redirectToRoute('app_customer_index');
        }

        $productIdRaw = $request->request->get('product_id');
        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $product = null;

        if (is_numeric($productIdRaw)) {
            $product = $productsRepository->find((int) $productIdRaw);
        }

        if (!$product instanceof Products && is_string($productIdRaw) && $productIdRaw !== '') {
            $product = $productsRepository->findOneBy(['name' => $productIdRaw]);
        }

        if (!$product instanceof Products && is_string($productIdRaw) && $productIdRaw !== '') {
            $requestedName = $this->normalizeProductName($productIdRaw);
            foreach ($productsRepository->findAll() as $candidate) {
                if ($this->normalizeProductName((string) $candidate->getName()) === $requestedName) {
                    $product = $candidate;
                    break;
                }
            }
        }

        if (!$product instanceof Products) {
            $catalogItem = $this->getMenuCatalogItem((string) $productIdRaw);
            if (null !== $catalogItem) {
                $product = new Products();
                $product->setName($catalogItem['name']);
                $product->setDescription($catalogItem['description']);
                $product->setPrice($catalogItem['price']);
                $entityManager->persist($product);
                $entityManager->flush();
            }
        }

        if (!$product instanceof Products) {
            $this->addFlash('error', 'Selected product is not available.');
            return $this->redirectToRoute('app_customer_index');
        }

        $order = new Orders();
        $order->setProduct($product);
        $order->setQuantity($quantity);
        $order->setTotalPrice($product->getPrice() * $quantity);
        $order->setStatus('SERVING');

        $entityManager->persist($order);
        $entityManager->flush();

        try {
            $currentUser = $this->getUser();
            if ($currentUser instanceof User) {
                $activityLog = new ActivityLog();
                $activityLog->setUser($currentUser);
                $activityLog->setAction('CREATE');
                $activityLog->setEntityType('Orders');
                $activityLog->setEntityId($order->getId());
                $activityLog->setUserRole('ROLE_USER');
                $entityManager->persist($activityLog);
                $entityManager->flush();
            }
        } catch (\Exception $e) {
            // Silently fail - don't break order creation
        }

        $this->addFlash('success', 'Order placed successfully!');
        return $this->redirectToRoute('app_customer_index');
    }

    private function normalizeProductName(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

        return $normalized ?? '';
    }

    private function getMenuCatalogItem(string $selectedName): ?array
    {
        $catalog = [
            ['name' => 'Iced Latte', 'category' => 'Our Best Sellers', 'price' => 4.50, 'description' => 'Cool and creamy espresso with milk over ice.'],
            ['name' => 'Caramel Macchiato', 'category' => 'Our Best Sellers', 'price' => 5.20, 'description' => 'Rich espresso layered with vanilla and caramel.'],
            ['name' => 'Mocha', 'category' => 'Our Best Sellers', 'price' => 4.80, 'description' => 'Espresso with chocolate and steamed milk.'],
            ['name' => 'Cappuccino', 'category' => 'Our Best Sellers', 'price' => 4.00, 'description' => 'Espresso topped with steamed milk foam.'],
            ['name' => 'Americano', 'category' => 'Our Best Sellers', 'price' => 3.80, 'description' => 'Smooth espresso diluted with hot water.'],
            ['name' => 'Frappuccino', 'category' => 'Our Best Sellers', 'price' => 5.50, 'description' => 'Blended iced coffee with sweet whipped cream.'],
            ['name' => 'Vanilla Latte', 'category' => 'Our Best Sellers', 'price' => 4.70, 'description' => 'Espresso mixed with steamed milk and vanilla syrup.'],
            ['name' => 'Hazelnut Cold Brew', 'category' => 'Our Best Sellers', 'price' => 5.10, 'description' => 'Chilled slow-brew coffee with hazelnut flavor.'],
            ['name' => 'Thai Tea', 'category' => 'Tea & Non-Coffee', 'price' => 4.50, 'description' => 'Black tea sweetened with condensed milk—iced or hot.'],
            ['name' => 'Chai Tea', 'category' => 'Tea & Non-Coffee', 'price' => 4.50, 'description' => 'Warm spices steeped with black tea and steamed milk.'],
            ['name' => 'Matcha Latte', 'category' => 'Tea & Non-Coffee', 'price' => 5.50, 'description' => 'Stone-ground green tea whisked with steamed milk.'],
            ['name' => 'Hot Chocolate', 'category' => 'Tea & Non-Coffee', 'price' => 4.50, 'description' => 'Rich cocoa with steamed milk and a cloud of whipped cream.'],
            ['name' => 'Herbal Tea', 'category' => 'Tea & Non-Coffee', 'price' => 3.50, 'description' => 'Caffeine-free botanical blend—ask for today’s selection.'],
            ['name' => 'Croissant', 'category' => 'Pastries & Snacks', 'price' => 3.50, 'description' => 'Buttery, flaky layers baked fresh for the morning rush.'],
            ['name' => 'Blueberry Muffin', 'category' => 'Pastries & Snacks', 'price' => 3.00, 'description' => 'Moist muffin studded with wild blueberries.'],
            ['name' => 'Chocolate Cookie', 'category' => 'Pastries & Snacks', 'price' => 2.50, 'description' => 'Soft-baked cookie with melty dark chocolate chunks.'],
            ['name' => 'Avocado Toast', 'category' => 'Pastries & Snacks', 'price' => 6.50, 'description' => 'Smashed avocado on artisan toast with sea salt and citrus.'],
        ];

        $normalizedSelected = $this->normalizeProductName($selectedName);
        foreach ($catalog as $item) {
            if ($this->normalizeProductName($item['name']) === $normalizedSelected) {
                return $item;
            }
        }

        return null;
    }

    #[Route('/orders/create-from-product/{id}', name: 'app_orders_create_from_product', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createFromProduct(
        Products $product,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('order-' . $product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid order request. Please try again.');
            return $this->redirectToRoute('app_products_show', ['id' => $product->getId()]);
        }

        $quantity = max(1, (int) $request->request->get('quantity', 0));
        if ($quantity < 1) {
            $sessionKey = 'product_order_quantity_' . $product->getId();
            if ($request->getSession()->has($sessionKey)) {
                $quantity = max(1, (int) $request->getSession()->get($sessionKey));
            } else {
                $quantity = 1;
            }
        }

        // Create a new order
        $order = new Orders();
        $order->setProduct($product);
        $order->setQuantity($quantity);
        $order->setTotalPrice($product->getPrice() * $quantity);
        $order->setStatus('SERVING');

        // Save the order
        $entityManager->persist($order);
        $entityManager->flush();

        $request->getSession()->set('product_order_quantity_' . $product->getId(), $quantity);

        // Only staff/admin submissions from "Order Now" should appear in transaction history.
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            try {
                $transaction = new \App\Entity\Transaction();
                $transaction->setOrder($order);
                $transaction->setAmount((float) $order->getTotalPrice());
                $transaction->setCurrency('USD');
                $transaction->setStatus($order->getStatus() ?? 'SERVING');
                $entityManager->persist($transaction);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Don't block order creation if transaction creation fails.
            }
        }

        // Manually create activity log as fallback
        try {
            $currentUser = $this->getUser();
            if ($currentUser instanceof User) {
                $activityLog = new ActivityLog();
                $activityLog->setUser($currentUser);
                $activityLog->setAction('CREATE');
                $activityLog->setEntityType('Orders');
                $activityLog->setEntityId($order->getId());
                
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
            // Silently fail - don't break order creation
        }

        $this->addFlash('success', 'Order placed successfully!');

        // Keep customer in Order Now page after placing an order
        return $this->redirectToRoute('app_products_index');
    }
}
