<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Payload;

use App\Catalog\Domain\Entity\Category;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Read-only projection of a category embedded in a product representation.
 */
final class CategoryReference
{
    #[Groups(['product:read'])]
    public string $id = '';

    #[Groups(['product:read'])]
    public string $code = '';

    public static function fromCategory(Category $category): self
    {
        $reference = new self();
        $reference->id = $category->getId()->toRfc4122();
        $reference->code = $category->getCode();

        return $reference;
    }
}
