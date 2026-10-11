<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\State\ProviderInterface;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Infrastructure\ApiPlatform\Mapper\ProductMapper;
use App\Catalog\Infrastructure\ApiPlatform\Resource\ProductResource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends EntityToResourceProvider<Product, ProductResource>
 */
final class ProductStateProvider extends EntityToResourceProvider
{
    /**
     * @param ProviderInterface<Product> $itemProvider
     * @param ProviderInterface<Product> $collectionProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        ProviderInterface $itemProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        ProviderInterface $collectionProvider,
        private readonly ProductMapper $mapper,
    ) {
        parent::__construct($itemProvider, $collectionProvider);
    }

    protected function map(object $entity): ProductResource
    {
        return $this->mapper->toResource($entity);
    }
}
