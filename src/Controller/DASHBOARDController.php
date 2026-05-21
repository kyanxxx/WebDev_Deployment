<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\OrdersRepository;
use App\Repository\ProductsRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

final class DASHBOARDController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_dashboard')]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"))]
    public function index(
        UserRepository $userRepository,
        ProductsRepository $productsRepository,
        OrdersRepository $ordersRepository,
        ActivityLogRepository $activityLogRepository
    ): Response {
        $stats = [];
        $recentActivities = [];

        // Only calculate stats for admins
        if ($this->isGranted('ROLE_ADMIN')) {
            // Total users (all users)
            $stats['totalUsers'] = $userRepository->count([]);
            
            // Total staff (users with ROLE_STAFF, excluding admins)
            // Get all users and filter in PHP since roles are stored as JSON array
            $allUsers = $userRepository->findAll();
            $staffCount = 0;
            foreach ($allUsers as $user) {
                $roles = $user->getRoles();
                if (in_array('ROLE_STAFF', $roles, true) && !in_array('ROLE_ADMIN', $roles, true)) {
                    $staffCount++;
                }
            }
            $stats['totalStaff'] = $staffCount;
            
            // Total products
            $stats['totalProducts'] = $productsRepository->count([]);
            
            // Total orders
            $stats['totalOrders'] = $ordersRepository->count([]);
            
            // Total records (products + orders)
            $stats['totalRecords'] = $stats['totalProducts'] + $stats['totalOrders'];
            
            // Recent activities (last 10)
            $recentActivities = $activityLogRepository->findRecent(10);
        }

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'recentActivities' => $recentActivities,
        ]);
    }

    #[Route('/admin/dashboard/stats', name: 'app_dashboard_stats', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"))]
    public function stats(
        UserRepository $userRepository,
        ProductsRepository $productsRepository,
        OrdersRepository $ordersRepository
    ): JsonResponse {
        $stats = [
            'totalUsers' => 0,
            'totalStaff' => 0,
            'totalProducts' => 0,
            'totalOrders' => 0,
            'totalRecords' => 0,
        ];

        if ($this->isGranted('ROLE_ADMIN')) {
            $stats['totalUsers'] = $userRepository->count([]);

            $allUsers = $userRepository->findAll();
            $staffCount = 0;
            foreach ($allUsers as $user) {
                $roles = $user->getRoles();
                if (in_array('ROLE_STAFF', $roles, true) && !in_array('ROLE_ADMIN', $roles, true)) {
                    $staffCount++;
                }
            }

            $stats['totalStaff'] = $staffCount;
            $stats['totalProducts'] = $productsRepository->count([]);
            $stats['totalOrders'] = $ordersRepository->count([]);
            $stats['totalRecords'] = $stats['totalProducts'] + $stats['totalOrders'];
        }

        return $this->json($stats);
    }

    #[Route('/admin/notifications/orders', name: 'app_dashboard_order_notifications', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"))]
    public function orderNotifications(
        OrdersRepository $ordersRepository,
        TransactionRepository $transactionRepository
    ): JsonResponse
    {
        $servedOrderRows = $transactionRepository->createQueryBuilder('t')
            ->select('IDENTITY(t.order) AS orderId')
            ->where('UPPER(t.status) = :servedStatus')
            ->setParameter('servedStatus', 'SERVED')
            ->getQuery()
            ->getScalarResult();

        $servedOrderIds = array_map(
            static fn (array $row): int => (int) $row['orderId'],
            $servedOrderRows
        );

        $qb = $ordersRepository->createQueryBuilder('o')
            ->leftJoin('o.product', 'p')
            ->where('o.status IN (:statuses) OR o.id IN (:servedIds)')
            ->setParameter('statuses', ['PENDING', 'Pending', 'SERVING', 'SERVED'])
            ->setParameter('servedIds', $servedOrderIds ?: [0])
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(10);

        $pendingOrders = $qb->getQuery()->getResult();

        $countQb = $ordersRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', ['PENDING', 'Pending', 'SERVING']);

        $pendingCount = (int) $countQb->getQuery()->getSingleScalarResult();

        $notifications = array_map(static function ($order) use ($servedOrderIds): array {
            $isServed = in_array($order->getId(), $servedOrderIds, true)
                || strtoupper((string) $order->getStatus()) === 'SERVED';

            return [
                'id' => $order->getId(),
                'productName' => $order->getProduct()?->getName() ?? 'Unknown product',
                'quantity' => $order->getQuantity(),
                'totalPrice' => $order->getTotalPrice(),
                'status' => $order->getStatus(),
                'isServed' => $isServed,
            ];
        }, $pendingOrders);

        return $this->json([
            'count' => $pendingCount,
            'notifications' => $notifications,
            'ordersUrl' => $this->generateUrl('app_order_form'),
        ]);
    }
}
