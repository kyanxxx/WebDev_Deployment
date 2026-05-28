<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\TransactionRepository;
use App\Service\MobilePushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/transaction-history')]
#[IsGranted(new Expression("is_granted('ROLE_ADMIN') or is_granted('ROLE_STAFF')"))]
class TransactionHistoryController extends AbstractController
{
    #[Route('/', name: 'app_transaction_history_index', methods: ['GET'])]
    public function index(TransactionRepository $transactionRepository): Response
    {
        $transactions = $transactionRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('transaction_history/index.html.twig', [
            'transactions' => $transactions,
        ]);
    }

    #[Route('/{id}/done', name: 'app_transaction_history_done', methods: ['POST'])]
    public function done(
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogRepository $activityLogRepository,
        MobilePushNotificationService $mobilePushNotificationService,
        \App\Entity\Transaction $transaction
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('transaction_done_' . $transaction->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request token.');
            return $this->redirectToRoute('app_transaction_history_index');
        }

        if (strtoupper((string) $transaction->getStatus()) === 'SERVING') {
            $transaction->setStatus('SERVED');

            $order = $transaction->getOrder();
            if (null !== $order && strtoupper((string) $order->getStatus()) !== 'SERVED') {
                $order->setStatus('SERVED');
            }

            $entityManager->flush();

            if (null !== $order) {
                $customer = $activityLogRepository->findLatestCustomerForOrderId((int) $order->getId());
                if (null !== $customer) {
                    $mobilePushNotificationService->sendOrderReadyNotification($customer, (int) $order->getId());
                }
            }

            $this->addFlash('success', 'Order marked as done.');
        } else {
            $this->addFlash('info', 'Order is already marked as done.');
        }

        return $this->redirectToRoute('app_transaction_history_index');
    }

    #[Route('/clear', name: 'app_transaction_history_clear', methods: ['POST'])]
    public function clear(Request $request, TransactionRepository $transactionRepository): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('clear_transactions', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid request token.');

            return $this->redirectToRoute('app_transaction_history_index');
        }

        $deletedCount = $transactionRepository->deleteAll();

        $this->addFlash(
            'success',
            $deletedCount > 0
                ? sprintf('Deleted %d transaction record(s).', $deletedCount)
                : 'No transaction records to delete.'
        );

        return $this->redirectToRoute('app_transaction_history_index');
    }
}

