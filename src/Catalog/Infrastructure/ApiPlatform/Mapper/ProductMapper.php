<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Mapper;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Infrastructure\ApiPlatform\Payload\CategoryReference;
use App\Catalog\Infrastructure\ApiPlatform\Payload\MoneyPayload;
use App\Catalog\Infrastructure\ApiPlatform\Resource\ProductResource;

final class ProductMapper
{
    public function toResource(Product $product): ProductResource
    {
        $resource = new ProductResource();
        $resource->id = $product->getId()->toRfc4122();
        $resource->name = $product->getName();
        $resource->price = MoneyPayload::fromMoney($product->getPrice());
        $resource->categoryCodes = $product->getCategoryCodes();
        $resource->categories = array_map(
            CategoryReference::fromCategory(...),
            $product->getCategories(),
        );
        $resource->createdAt = $product->getCreatedAt();
        $resource->updatedAt = $product->getUpdatedAt();

        return $resource;
    }
}
