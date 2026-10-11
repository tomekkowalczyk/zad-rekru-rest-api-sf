<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Event\ProductSaved;
use App\Catalog\Domain\Exception\InvalidProductName;
use App\Catalog\Domain\Exception\ProductMustBelongToACategory;
use App\Shared\Domain\Entity\AggregateRoot;
use App\Shared\Domain\Entity\TimestampableTrait;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product')]
// Backs the default ordering of the product collection.
#[ORM\Index(name: 'idx_product_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
final class Product extends AggregateRoot
{
    use TimestampableTrait;

    public const int NAME_MAX_LENGTH = 255;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, fetch: 'EXTRA_LAZY')]
    #[ORM\JoinTable(name: 'product_category')]
    // RESTRICT: a cascade would silently strip products of their last category.
    #[ORM\InverseJoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[ORM\OrderBy(['code' => 'ASC'])]
    private Collection $categories;

    /**
     * @param non-empty-list<Category> $categories
     *
     * @throws InvalidProductName
     * @throws ProductMustBelongToACategory
     */
    public function __construct(#[ORM\Column(type: 'string', length: self::NAME_MAX_LENGTH)]
        private string $name, #[ORM\Embedded(class: Money::class, columnPrefix: 'price_')]
        private Money $price, array $categories, ?Uuid $id = null)
    {
        self::assertValidName($name);
        $this->id = $id ?? Uuid::v7();
        $this->categories = new ArrayCollection();

        $this->applyCategories($categories);
        $this->recordChange(wasCreated: true);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    /**
     * @return list<Category>
     */
    public function getCategories(): array
    {
        return array_values($this->categories->toArray());
    }

    /**
     * @return list<string>
     */
    public function getCategoryCodes(): array
    {
        return array_map(static fn (Category $category): string => $category->getCode(), $this->getCategories());
    }

    /**
     * @throws InvalidProductName
     */
    public function rename(string $name): void
    {
        if ($name === $this->name) {
            return;
        }

        self::assertValidName($name);

        $this->name = $name;
        $this->recordChange();
    }

    public function changePrice(Money $price): void
    {
        if ($price->equals($this->price)) {
            return;
        }

        $this->price = $price;
        $this->recordChange();
    }

    /**
     * @param non-empty-list<Category> $categories
     *
     * @throws ProductMustBelongToACategory
     */
    public function replaceCategories(array $categories): void
    {
        if ($this->applyCategories($categories)) {
            $this->recordChange();
        }
    }

    /**
     * @param list<Category> $categories
     *
     * @return bool whether the collection actually changed
     *
     * @throws ProductMustBelongToACategory
     */
    private function applyCategories(array $categories): bool
    {
        if ([] === $categories) {
            throw ProductMustBelongToACategory::create();
        }

        $deduplicated = [];
        foreach ($categories as $category) {
            $deduplicated[$category->getId()->toRfc4122()] = $category;
        }

        if ($this->sameCategoriesAs($deduplicated)) {
            return false;
        }

        $this->categories->clear();
        foreach ($deduplicated as $category) {
            $this->categories->add($category);
        }

        return true;
    }

    /**
     * @param array<string, Category> $candidates
     */
    private function sameCategoriesAs(array $candidates): bool
    {
        if (\count($candidates) !== $this->categories->count()) {
            return false;
        }

        foreach ($this->categories as $category) {
            if (!isset($candidates[$category->getId()->toRfc4122()])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws InvalidProductName
     */
    private static function assertValidName(string $name): void
    {
        if ('' === trim($name)) {
            throw InvalidProductName::blank();
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw InvalidProductName::tooLong($name, self::NAME_MAX_LENGTH);
        }
    }

    private function recordChange(bool $wasCreated = false): void
    {
        if (!$wasCreated) {
            $this->touch();
        }

        if (!$this->hasRecorded(ProductSaved::class)) {
            $this->recordThat(new ProductSaved($this->id, $wasCreated));
        }
    }
}
