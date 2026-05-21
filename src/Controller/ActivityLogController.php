<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-log')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('/', name: 'app_activity_log_index', methods: ['GET'])]
    public function index(
        ActivityLogRepository $activityLogRepository,
        Request $request
    ): Response {
        // Get filter parameters
        $action = $request->query->get('action');
        $entityType = $request->query->get('entity_type');
        $userId = $request->query->get('user_id');

        // Build query
        $qb = $activityLogRepository->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        // Apply filters
        if ($action) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }

        if ($entityType) {
            $qb->andWhere('a.entityType = :entityType')
               ->setParameter('entityType', $entityType);
        }

        if ($userId) {
            $qb->andWhere('a.user = :userId')
               ->setParameter('userId', $userId);
        }

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $totalLogs = count($qb->getQuery()->getResult());
        $totalPages = ceil($totalLogs / $limit);

        $logs = $qb->setMaxResults($limit)
                   ->setFirstResult($offset)
                   ->getQuery()
                   ->getResult();

        // Get unique values for filters
        $allActions = $activityLogRepository->createQueryBuilder('a')
            ->select('DISTINCT a.action')
            ->orderBy('a.action', 'ASC')
            ->getQuery()
            ->getResult();

        $allEntityTypes = $activityLogRepository->createQueryBuilder('a')
            ->select('DISTINCT a.entityType')
            ->orderBy('a.entityType', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('activity_log/index.html.twig', [
            'logs' => $logs,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
            'actions' => array_column($allActions, 'action'),
            'entityTypes' => array_column($allEntityTypes, 'entityType'),
            'currentAction' => $action,
            'currentEntityType' => $entityType,
            'currentUserId' => $userId,
        ]);
    }

    #[Route('/clear', name: 'app_activity_log_clear', methods: ['POST'])]
    public function clear(
        ActivityLogRepository $activityLogRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        // Verify CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('clear_activity_logs', $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_activity_log_index');
        }

        // Delete all activity logs
        $logs = $activityLogRepository->findAll();
        foreach ($logs as $log) {
            $entityManager->remove($log);
        }
        $entityManager->flush();

        $this->addFlash('success', 'All activity logs have been cleared successfully.');
        return $this->redirectToRoute('app_activity_log_index');
    }
}

