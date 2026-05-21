<?php

namespace App\EventSubscriber;

use App\Entity\Orders;
use App\Entity\Products;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Aligns mobile/API customer orders with the web customer flow
 * (CustomerOrderController::placeCustomerOrder).
 */
#[AsDoctrineListener(event: Events::prePersist)]
final class CustomerOrderSubscriber
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Orders) {
            return;
        }

        if (!$this->isCustomerUser()) {
            return;
        }

        $entity->setStatus('SERVING');

        $product = $entity->getProduct();
        $quantity = max(1, (int) ($entity->getQuantity() ?? 1));
        $entity->setQuantity($quantity);

        if ($product instanceof Products && $product->getPrice() !== null) {
            $entity->setTotalPrice($product->getPrice() * $quantity);
        }
    }

    private function isCustomerUser(): bool
    {
        return $this->security->isGranted('ROLE_USER')
            && !$this->security->isGranted('ROLE_ADMIN')
            && !$this->security->isGranted('ROLE_STAFF');
    }
}
