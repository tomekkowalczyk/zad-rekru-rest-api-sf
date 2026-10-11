<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Mapper;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Infrastructure\ApiPlatform\Resource\CategoryResource;

final class CategoryMapper
{
    public function toResource(Category $category): CategoryResource
    {
        $resource = new CategoryResource();
        $resource->id = $category->getId()->toRfc4122();
        $resource->code = $category->getCode();
        $resource->createdAt = $category->getCreatedAt();
        $resource->updatedAt = $category->getUpdatedAt();

        return $resource;
    }
}
