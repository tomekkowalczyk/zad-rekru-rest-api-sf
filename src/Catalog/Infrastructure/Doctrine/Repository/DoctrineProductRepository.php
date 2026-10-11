<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Deliberately *not* a `ServiceEntityRepository`: the domain interface owns the
 * contract, Doctrine stays an implementation detail hidden behind it.
 */
final readonly class DoctrineProductRepository implements ProductRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $id): ?Product
    {
        return $this->entityManager->find(Product::class, $id);
    }

    /**
     * The flush happens inside an explicit transaction on purpose: the domain
     * events are published from `postFlush`, while that transaction is still
     * open, and the notification messages they produce are inserted into the
     * Doctrine queue through the same connection. The product and its queued
     * notifications are therefore committed — or rolled back — together
     * (transactional outbox, ADR-0003).
     */
    public function save(Product $product): void
    {
        $this->entityManager->wrapInTransaction(
            static fn (EntityManagerInterface $entityManager) => $entityManager->persist($product),
        );
    }

    public function remove(Product $product): void
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }
}
