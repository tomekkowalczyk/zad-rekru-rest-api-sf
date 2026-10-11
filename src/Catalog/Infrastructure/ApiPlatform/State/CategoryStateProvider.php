<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\State\ProviderInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Infrastructure\ApiPlatform\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\ApiPlatform\Resource\CategoryResource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends EntityToResourceProvider<Category, CategoryResource>
 */
final class CategoryStateProvider extends EntityToResourceProvider
{
    /**
     * @param ProviderInterface<Category> $itemProvider
     * @param ProviderInterface<Category> $collectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        ProviderInterface $collectionProvider,
        private readonly CategoryMapper $mapper,
    ) {
        parent::__construct($itemProvider, $collectionProvider);
    }

    protected function map(object $entity): CategoryResource
    {
        return $this->mapper->toResource($entity);
    }
}
