<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Product;
use Symfony\Component\Uid\Uuid;

interface ProductRepository
{
    public function find(Uuid $id): ?Product;

    public function save(Product $product): void;

    public function remove(Product $product): void;
}
