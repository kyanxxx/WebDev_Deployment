<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Orders;
use App\Entity\Transaction;
use App\Entity\User;
use App\Form\OrderType;
use App\Repository\OrdersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/orders')]
#[IsGranted('ROLE_STAFF')]
class OrderController extends AbstractController
{
    #[Route('/', name: 'app_orders_index', methods: ['GET'])]
    public function index(OrdersRepository $ordersRepository): Response
    {
        // Get all orders, ordered by ID descending (newest first)
        $orders = $ordersRepository->findBy([], ['id' => 'DESC']);
        
        return $this->render('orders/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/new', name: 'app_orders_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $order = new Orders();
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
        $product = $order->getProduct();
        $quantity = $order->getQuantity();

        // 💰 Calculate total price based on product price
        $totalPrice = $product->getPrice() * $quantity;
        $order->setTotalPrice($totalPrice);

        $entityManager->persist($order);
        $entityManager->flush();

        // Create a transaction record for this order (admin/staff only view).
        try {
            $transaction = new Transaction();
            $transaction->setOrder($order);
            $transaction->setAmount((float) $totalPrice);
            $transaction->setCurrency('USD');
            $transaction->setStatus('SERVING');
            $entityManager->persist($transaction);
            $entityManager->flush();
        } catch (\Exception $e) {
            // Don't block order creation if transaction creation fails.
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

        $this->addFlash('success', 'Order created successfully!');
        return $this->redirectToRoute('app_orders_index');
    }


        return $this->render('orders/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_orders_show', methods: ['GET'])]
    public function show(Orders $order): Response
    {
        return $this->render('products/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_orders_delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $request, Orders $order, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            // Store order info before deletion
            $orderId = $order->getId();
            $productName = $order->getProduct() ? $order->getProduct()->getName() : 'N/A';
            $currentUser = $this->getUser();
            
            // Get user role before deletion
            $userRole = 'ROLE_USER';
            if ($currentUser instanceof User) {
                $roles = $currentUser->getRoles();
                if (in_array('ROLE_ADMIN', $roles, true)) {
                    $userRole = 'ROLE_ADMIN';
                } elseif (in_array('ROLE_STAFF', $roles, true)) {
                    $userRole = 'ROLE_STAFF';
                }
            }
            
            // Create activity log BEFORE deletion
            if ($currentUser instanceof User) {
                try {
                    $activityLog = new ActivityLog();
                    $activityLog->setUser($currentUser);
                    $activityLog->setAction('DELETE');
                    $activityLog->setEntityType('Orders');
                    $activityLog->setEntityId($orderId);
                    $activityLog->setUserRole($userRole);
                    
                    $entityManager->persist($activityLog);
                    $entityManager->flush();
                } catch (\Exception $e) {
                    error_log('ActivityLog error on order delete: ' . $e->getMessage());
                }
            }
            
            // Now delete the order
            $entityManager->remove($order);
            $entityManager->flush();
            
            // Add success message after deletion
            $this->addFlash('success', '✅ Order #' . $orderId . ' (' . $productName . ') has been deleted successfully.');
        }

        return $this->redirectToRoute('app_orders_index');
    }

}
