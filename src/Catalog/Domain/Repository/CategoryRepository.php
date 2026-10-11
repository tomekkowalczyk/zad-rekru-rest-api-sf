<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\CategoryStillInUse;
use App\Catalog\Domain\Exception\DuplicateCategoryCode;
use Symfony\Component\Uid\Uuid;

interface CategoryRepository
{
    public function find(Uuid $id): ?Category;

    public function findOneByCode(string $code): ?Category;

    /**
     * @param list<string> $codes
     *
     * @return list<Category>
     */
    public function findByCodes(array $codes): array;

    /**
     * @throws DuplicateCategoryCode
     */
    public function save(Category $category): void;

    /**
     * @throws CategoryStillInUse
     */
    public function remove(Category $category): void;
}
