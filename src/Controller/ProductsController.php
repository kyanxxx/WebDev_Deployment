<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Products;
use App\Entity\User;
use App\Form\ProductsType;
use App\Repository\OrdersRepository;
use App\Repository\ProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

#[Route(path: '/products')]
final class ProductsController extends AbstractController
{
    #[Route(name: 'app_products_index', methods: ['GET'])]
    public function index(Request $request, ProductsRepository $productsRepository): Response
    {
        $query = $request->query->get('q', '');
        $query = trim($query);
        
        if (!empty($query)) {
            $products = $productsRepository->search($query);
        } else {
            $products = $productsRepository->findAll();
        }
        
        return $this->render('products/index.html.twig', [
            'products' => $products,
            'searchQuery' => $query,
        ]);
    }

    #[Route('/menu', name: 'app_products_menu', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function menu(ProductsRepository $productsRepository): Response
    {
        return $this->render('products/menu.html.twig', [
            'products' => $productsRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_products_new', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"), message: 'Access Denied. The user doesn\'t have any of ROLE_ADMIN, ROLE_STAFF.')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Products();
        $form = $this->createForm(ProductsType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            // Manually create activity log as fallback (Staff/Admin creates product)
            try {
                $currentUser = $this->getUser();
                if ($currentUser instanceof User) {
                    $activityLog = new ActivityLog();
                    $activityLog->setUser($currentUser);
                    $activityLog->setAction('CREATE');
                    $activityLog->setEntityType('Products');
                    $activityLog->setEntityId($product->getId());
                    
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
                // Silently fail
            }

            $this->addFlash('success', 'Product created successfully!');
            return $this->redirectToRoute('app_products_index');
        }

        return $this->render('products/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/products/{id}', name: 'app_products_show', methods: ['GET'])]
    public function show(?Products $product, OrdersRepository $ordersRepository, SessionInterface $session): Response
    {
    if (!$product) {
        $this->addFlash('error', '⚠️ Product not found or has been deleted.');
        return $this->redirectToRoute('app_products_index');
    }

    $orderQuantity = $this->resolveOrderQuantity($product, $ordersRepository, $session);

    return $this->render('products/show.html.twig', [
        'product' => $product,
        'orderQuantity' => $orderQuantity,
        'totalPrice' => $product->getPrice() * $orderQuantity,
    ]);
    }


    #[Route('/{id}/edit', name: 'app_products_edit', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"), message: 'Access Denied. The user doesn\'t have any of ROLE_ADMIN, ROLE_STAFF.')]
    public function edit(
        Request $request,
        Products $product,
        EntityManagerInterface $entityManager,
        OrdersRepository $ordersRepository,
        SessionInterface $session,
    ): Response {
    $form = $this->createForm(ProductsType::class, $product);
    $form->handleRequest($request);

    $orderQuantity = $this->resolveOrderQuantity($product, $ordersRepository, $session);

    if ($form->isSubmitted() && $form->isValid()) {
        $submittedQuantity = max(1, (int) $request->request->get('order_quantity', $orderQuantity));
        $this->storeOrderQuantity($product, $submittedQuantity, $session);

        $entityManager->flush();

        // Manually create activity log as fallback (Staff/Admin edits product)
        try {
            $currentUser = $this->getUser();
            if ($currentUser instanceof User) {
                $activityLog = new ActivityLog();
                $activityLog->setUser($currentUser);
                $activityLog->setAction('UPDATE');
                $activityLog->setEntityType('Products');
                $activityLog->setEntityId($product->getId());
                
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
            // Silently fail
        }

        $this->addFlash('success', 'Updated Successfully');
        return $this->redirectToRoute('app_products_index', [], Response::HTTP_SEE_OTHER);
    }

    return $this->render('products/edit.html.twig', [
        'product' => $product,
        'form' => $form,
        'orderQuantity' => $orderQuantity,
    ]);
}

    private function orderQuantitySessionKey(Products $product): string
    {
        return 'product_order_quantity_' . $product->getId();
    }

    private function storeOrderQuantity(Products $product, int $quantity, SessionInterface $session): void
    {
        $session->set($this->orderQuantitySessionKey($product), max(1, $quantity));
    }

    private function resolveOrderQuantity(Products $product, OrdersRepository $ordersRepository, SessionInterface $session): int
    {
        $sessionKey = $this->orderQuantitySessionKey($product);
        if ($session->has($sessionKey)) {
            return max(1, (int) $session->get($sessionKey));
        }

        $latestOrder = $ordersRepository->findOneBy(
            ['product' => $product],
            ['id' => 'DESC']
        );

        if ($latestOrder instanceof \App\Entity\Orders && $latestOrder->getQuantity() > 0) {
            return $latestOrder->getQuantity();
        }

        return 1;
    }


        #[Route('/{id}', name: 'app_products_delete', methods: ['POST' , 'DELETE'])]
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"), message: 'Access Denied. The user doesn\'t have any of ROLE_ADMIN, ROLE_STAFF.')]
    public function delete(Request $request, Products $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            // Store product info before deletion
            $productId = $product->getId();
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
                    $activityLog->setEntityType('Products');
                    $activityLog->setEntityId($productId);
                    $activityLog->setUserRole($userRole);
                    
                    $entityManager->persist($activityLog);
                    $entityManager->flush();
                } catch (\Exception $e) {
                    error_log('ActivityLog error on product delete: ' . $e->getMessage());
                }
            }
            
            // Store product name for success message
            $productName = $product->getName();
            
            // Now delete the product
            $entityManager->remove($product);
            $entityManager->flush();
            
            // Add success message after deletion
            $this->addFlash('success', 'Product "' . $productName . '" has been deleted successfully.');
        }

        return $this->redirectToRoute('app_products_index');
    }


    
}


    