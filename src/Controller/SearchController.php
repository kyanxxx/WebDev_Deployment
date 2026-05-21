<?php

namespace App\Controller;

use App\Repository\ProductsRepository;
use App\Repository\OrdersRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/search')]
#[IsGranted('ROLE_USER')]
class SearchController extends AbstractController
{
    #[Route('/api', name: 'app_search_api', methods: ['GET'])]
    public function searchApi(
        Request $request,
        ProductsRepository $productsRepository,
        OrdersRepository $ordersRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $query = $request->query->get('q', '');
        $query = trim($query);
        $context = $request->query->get('context', 'all');
        
        $results = [
            'products' => [],
            'orders' => [],
            'users' => [],
        ];
        
        // Search based on context
        switch ($context) {
            case 'products':
                // Search all products - the search method will find all matching products
                $products = $productsRepository->search($query);
                foreach ($products as $product) {
                    $results['products'][] = [
                        'id' => $product->getId(),
                        'name' => $product->getName(),
                        'description' => $product->getDescription(),
                        'price' => $product->getPrice(),
                        'url' => $this->generateUrl('app_products_show', ['id' => $product->getId()]),
                    ];
                }
                break;
                
            case 'orders':
                if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
                    $orders = $ordersRepository->search($query);
                    foreach ($orders as $order) {
                        $results['orders'][] = [
                            'id' => $order->getId(),
                            'status' => $order->getStatus(),
                            'quantity' => $order->getQuantity(),
                            'totalPrice' => $order->getTotalPrice(),
                            'productName' => $order->getProduct() ? $order->getProduct()->getName() : 'N/A',
                            'url' => $this->generateUrl('app_orders_show', ['id' => $order->getId()]),
                        ];
                    }
                }
                break;
                
            case 'users':
                if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
                    $users = $userRepository->search($query);
                    foreach ($users as $user) {
                        $results['users'][] = [
                            'id' => $user->getId(),
                            'username' => $user->getUsername(),
                            'email' => $user->getEmail(),
                            'role' => $user->isAdmin() ? 'Admin' : ($user->isStaff() ? 'Staff' : 'User'),
                            'status' => $user->getStatus() ?? 'active',
                            'createdAt' => $user->getCreatedAt() ? $user->getCreatedAt()->format('Y-m-d H:i') : null,
                            'url' => $this->generateUrl('app_user_show', ['id' => $user->getId()]),
                        ];
                    }
                }
                break;
                
            case 'dashboard':
            case 'all':
            default:
                // Search all entities
                $products = $productsRepository->search($query);
                foreach ($products as $product) {
                    $results['products'][] = [
                        'id' => $product->getId(),
                        'name' => $product->getName(),
                        'description' => $product->getDescription(),
                        'price' => $product->getPrice(),
                        'url' => $this->generateUrl('app_products_show', ['id' => $product->getId()]),
                    ];
                }
                
                if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
                    $orders = $ordersRepository->search($query);
                    foreach ($orders as $order) {
                        $results['orders'][] = [
                            'id' => $order->getId(),
                            'status' => $order->getStatus(),
                            'quantity' => $order->getQuantity(),
                            'totalPrice' => $order->getTotalPrice(),
                            'productName' => $order->getProduct() ? $order->getProduct()->getName() : 'N/A',
                            'url' => $this->generateUrl('app_orders_show', ['id' => $order->getId()]),
                        ];
                    }
                }
                
                if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
                    $users = $userRepository->search($query);
                    foreach ($users as $user) {
                        $results['users'][] = [
                            'id' => $user->getId(),
                            'username' => $user->getUsername(),
                            'email' => $user->getEmail(),
                            'role' => $user->isAdmin() ? 'Admin' : ($user->isStaff() ? 'Staff' : 'User'),
                            'status' => $user->getStatus() ?? 'active',
                            'createdAt' => $user->getCreatedAt() ? $user->getCreatedAt()->format('Y-m-d H:i') : null,
                            'url' => $this->generateUrl('app_user_show', ['id' => $user->getId()]),
                        ];
                    }
                }
                break;
        }
        
        return new JsonResponse($results);
    }

    #[Route('', name: 'app_search', methods: ['GET'])]
    public function search(
        Request $request,
        ProductsRepository $productsRepository,
        OrdersRepository $ordersRepository,
        UserRepository $userRepository
    ): Response {
        $query = $request->query->get('q', '');
        $query = trim($query);
        
        // Get current route to determine context
        $currentRoute = $request->query->get('context', '');
        $referer = $request->headers->get('referer', '');
        
        // Determine context from referer if not provided
        if (empty($currentRoute)) {
            if (strpos($referer, '/products') !== false) {
                $currentRoute = 'products';
            } elseif (strpos($referer, '/orders') !== false || strpos($referer, '/admin/orders') !== false) {
                $currentRoute = 'orders';
            } elseif (strpos($referer, '/admin/users') !== false) {
                $currentRoute = 'users';
            } elseif (strpos($referer, '/admin/dashboard') !== false) {
                $currentRoute = 'dashboard';
            } else {
                $currentRoute = 'all';
            }
        }
        
        $results = [
            'products' => [],
            'orders' => [],
            'users' => [],
        ];
        
        if (empty($query)) {
            return $this->render('search/results.html.twig', [
                'query' => '',
                'context' => $currentRoute,
                'results' => $results,
            ]);
        }
        
        // Search based on context
        switch ($currentRoute) {
            case 'products':
                $results['products'] = $productsRepository->search($query);
                break;
                
            case 'orders':
                if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
                    $results['orders'] = $ordersRepository->search($query);
                }
                break;
                
            case 'users':
                if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
                    $results['users'] = $userRepository->search($query);
                }
                break;
                
            case 'dashboard':
            case 'all':
            default:
                // Search all entities
                $results['products'] = $productsRepository->search($query);
                
                if ($this->isGranted('ROLE_STAFF') || $this->isGranted('ROLE_ADMIN')) {
                    $results['orders'] = $ordersRepository->search($query);
                }
                
                if ($this->isGranted('ROLE_ADMIN')) {
                    $results['users'] = $userRepository->search($query);
                }
                break;
        }
        
        return $this->render('search/results.html.twig', [
            'query' => $query,
            'context' => $currentRoute,
            'results' => $results,
        ]);
    }
}

