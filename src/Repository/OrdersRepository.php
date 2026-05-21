<?php

namespace App\Repository;

use App\Entity\Orders;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Orders>
 */
class OrdersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Orders::class);
    }

    //    /**
    //     * @return Orders[] Returns an array of Orders objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Orders
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Search orders by ID, status, or product name
     */
    public function search(string $query): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.product', 'p');
        
        // Try to match as order ID if query is numeric
        if (is_numeric($query)) {
            $qb->where('o.id = :id')
                ->setParameter('id', (int)$query);
        } else {
            $qb->where('o.status LIKE :query')
                ->orWhere('p.name LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }
        
        return $qb->orderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
