<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Catalog\Domain\Entity\Product;
use Doctrine\ORM\QueryBuilder;

/**
 * Every product representation embeds its categories, so they are fetch-joined
 * instead of lazily loaded one product at a time (N+1 on the collection).
 *
 * The API Platform paginator detects the to-many join and paginates on
 * distinct product identifiers, so page sizes stay correct.
 */
final class EagerLoadProductCategoriesExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->joinCategories($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->joinCategories($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function joinCategories(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        if (Product::class !== $resourceClass) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $categoryAlias = $queryNameGenerator->generateJoinAlias('categories');

        $queryBuilder
            ->leftJoin(\sprintf('%s.categories', $rootAlias), $categoryAlias)
            ->addSelect($categoryAlias);
    }
}
