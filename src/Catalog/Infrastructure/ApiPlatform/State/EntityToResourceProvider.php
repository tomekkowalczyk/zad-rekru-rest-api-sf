<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;

/**
 * Reads entities with the stock Doctrine providers (so pagination, ordering and
 * filters keep working) and maps them to read models.
 *
 * @template TEntity of object
 * @template TResource of object
 *
 * @implements ProviderInterface<TResource>
 */
abstract class EntityToResourceProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<TEntity> $itemProvider
     * @param ProviderInterface<TEntity> $collectionProvider
     */
    public function __construct(
        private readonly ProviderInterface $itemProvider,
        private readonly ProviderInterface $collectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($operation, $uriVariables, $context);
        }

        $entity = $this->itemProvider->provide($operation, $uriVariables, $context);

        if (null === $entity) {
            return null;
        }

        \assert(\is_object($entity));

        /** @var TEntity $entity */
        return $this->map($entity);
    }

    /**
     * @param TEntity $entity
     *
     * @return TResource
     */
    abstract protected function map(object $entity): object;

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     *
     * @return iterable<int, TResource>
     */
    private function provideCollection(Operation $operation, array $uriVariables, array $context): iterable
    {
        /** @var iterable<array-key, TEntity>|PaginatorInterface<TEntity>|null $entities */
        $entities = $this->collectionProvider->provide($operation, $uriVariables, $context);

        if (null === $entities) {
            return [];
        }

        $resources = [];
        foreach ($entities as $entity) {
            $resources[] = $this->map($entity);
        }

        if (!$entities instanceof PaginatorInterface) {
            return $resources;
        }

        return new TraversablePaginator(
            new \ArrayIterator($resources),
            $entities->getCurrentPage(),
            $entities->getItemsPerPage(),
            $entities->getTotalItems(),
        );
    }
}
