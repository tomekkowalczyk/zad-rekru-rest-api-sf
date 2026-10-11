<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Exception\CategoryStillInUse;
use App\Catalog\Domain\Exception\DuplicateCategoryCode;
use App\Catalog\Domain\Repository\CategoryRepository;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Database constraints are the last line of defence when validation loses a
 * race; these tests make sure they surface as domain errors, not as a 500.
 */
final class DoctrineCategoryRepositoryTest extends KernelTestCase
{
    private CategoryRepository $categories;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->categories = self::getContainer()->get(CategoryRepository::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    #[Test]
    public function aDuplicateCodeThatSlippedPastValidationIsADomainError(): void
    {
        $this->categories->save(new Category('RACE'));

        $this->expectException(DuplicateCategoryCode::class);

        $this->categories->save(new Category('RACE'));
    }

    #[Test]
    public function aCategoryAssignedToAProductCannotBeRemoved(): void
    {
        $category = new Category('INUSE');
        $this->entityManager->persist($category);
        $this->entityManager->persist(new Product('Keyboard', new Money(100, 'PLN'), [$category]));
        $this->entityManager->flush();

        $this->expectException(CategoryStillInUse::class);

        $this->categories->remove($category);
    }

    #[Test]
    public function anUnusedCategoryCanBeRemoved(): void
    {
        $category = new Category('UNUSED');
        $this->categories->save($category);

        $this->categories->remove($category);

        self::assertNull($this->categories->findOneByCode('UNUSED'));
    }
}
