<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\CategoryStillInUse;
use App\Catalog\Domain\Exception\DuplicateCategoryCode;
use App\Catalog\Domain\Repository\CategoryRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineCategoryRepository implements CategoryRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function find(Uuid $id): ?Category
    {
        return $this->entityManager->find(Category::class, $id);
    }

    public function findOneByCode(string $code): ?Category
    {
        return $this->entityManager->getRepository(Category::class)->findOneBy(['code' => $code]);
    }

    public function findByCodes(array $codes): array
    {
        if ([] === $codes) {
            return [];
        }

        /** @var list<Category> $categories */
        $categories = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Category::class, 'c')
            ->where('c.code IN (:codes)')
            ->setParameter('codes', array_values(array_unique($codes)))
            ->orderBy('c.code', 'ASC')
            ->getQuery()
            ->getResult();

        return $categories;
    }

    public function save(Category $category): void
    {
        try {
            $this->entityManager->persist($category);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw DuplicateCategoryCode::withCode($category->getCode(), $exception);
        }
    }

    public function remove(Category $category): void
    {
        // The RESTRICT foreign key on product_category is the source of truth:
        // unlike a pre-check SELECT, it cannot lose a race with a concurrent save.
        try {
            $this->entityManager->remove($category);
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException $exception) {
            throw CategoryStillInUse::withCode($category->getCode(), $exception);
        }
    }
}
