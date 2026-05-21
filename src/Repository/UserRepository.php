<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Search users by username, email, id, status, role, or created date
     */
    public function search(string $query): array
    {
        $qb = $this->createQueryBuilder('u');
        
        // If query is empty or '*', return all users
        if (empty($query) || $query === '*') {
            return $qb
                ->orderBy('u.username', 'ASC')
                ->getQuery()
                ->getResult();
        }
        
        $searchTerm = '%' . $query . '%';
        $isNumeric = is_numeric($query);
        
        // Search across multiple fields
        $qb->where('LOWER(u.username) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.email) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.status) LIKE LOWER(:query)');
        
        // If query is numeric, also search by ID
        if ($isNumeric) {
            $qb->orWhere('u.id = :id')
                ->setParameter('id', (int)$query);
        }
        
        $qb->setParameter('query', $searchTerm)
            ->orderBy('u.username', 'ASC');
        
        $users = $qb->getQuery()->getResult();
        
        // Filter by role and date in PHP (since Doctrine doesn't handle array/date searches well)
        if (!empty($users) && !$isNumeric) {
            $filteredUsers = [];
            $lowerQuery = strtolower($query);
            
            foreach ($users as $user) {
                $match = false;
                
                // Check if already matched by SQL query
                if (stripos($user->getUsername() ?? '', $query) !== false ||
                    stripos($user->getEmail() ?? '', $query) !== false ||
                    stripos($user->getStatus() ?? '', $query) !== false) {
                    $match = true;
                }
                
                // Check role
                if (!$match) {
                    $roles = $user->getRoles();
                    foreach ($roles as $role) {
                        if (stripos(strtolower($role), $lowerQuery) !== false ||
                            stripos(strtolower($role === 'ROLE_ADMIN' ? 'Admin' : ($role === 'ROLE_STAFF' ? 'Staff' : 'User')), $lowerQuery) !== false) {
                            $match = true;
                            break;
                        }
                    }
                }
                
                // Check created date
                if (!$match && $user->getCreatedAt()) {
                    $dateStr = $user->getCreatedAt()->format('Y-m-d');
                    $year = $user->getCreatedAt()->format('Y');
                    $month = $user->getCreatedAt()->format('m');
                    $day = $user->getCreatedAt()->format('d');
                    
                    if (stripos($dateStr, $query) !== false ||
                        stripos($year, $query) !== false ||
                        stripos($month, $query) !== false ||
                        stripos($day, $query) !== false) {
                        $match = true;
                    }
                }
                
                if ($match) {
                    $filteredUsers[] = $user;
                }
            }
            
            return $filteredUsers;
        }
        
        return $users;
    }
}
