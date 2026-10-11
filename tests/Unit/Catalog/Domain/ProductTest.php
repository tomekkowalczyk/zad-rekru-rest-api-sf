<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Event\ProductSaved;
use App\Catalog\Domain\Exception\InvalidProductName;
use App\Catalog\Domain\Exception\ProductMustBelongToACategory;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

#[CoversClass(Product::class)]
final class ProductTest extends TestCase
{
    use ClockSensitiveTrait;

    #[Test]
    public function itCannotExistWithoutACategory(): void
    {
        $this->expectException(ProductMustBelongToACategory::class);

        /* @phpstan-ignore argument.type (the point of the test is to break the type contract at runtime) */
        new Product('Keyboard', new Money(100, 'PLN'), []);
    }

    #[Test]
    public function itCannotLoseItsLastCategory(): void
    {
        $product = self::product();

        $this->expectException(ProductMustBelongToACategory::class);

        /* @phpstan-ignore argument.type (the point of the test is to break the type contract at runtime) */
        $product->replaceCategories([]);
    }

    #[Test]
    public function itRecordsExactlyOneSaveEventPerTransaction(): void
    {
        $product = self::product();
        $product->rename('Gaming keyboard');
        $product->changePrice(new Money(49900, 'PLN'));
        $product->replaceCategories([new Category('GAMING')]);

        $events = $product->releaseEvents();

        self::assertCount(1, $events, 'Several field changes must still trigger a single notification.');
        self::assertInstanceOf(ProductSaved::class, $events[0]);
        self::assertTrue($events[0]->wasCreated);
        self::assertSame($product->getId(), $events[0]->productId);
        self::assertSame([], $product->releaseEvents(), 'Events are released only once.');
    }

    #[Test]
    public function itRecordsAnUpdateEventAfterTheCreationOneWasReleased(): void
    {
        $product = self::product();
        $product->releaseEvents();

        $product->rename('Gaming keyboard');
        $events = $product->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ProductSaved::class, $events[0]);
        self::assertFalse($events[0]->wasCreated);
    }

    #[Test]
    public function itStaysSilentWhenNothingActuallyChanges(): void
    {
        $category = new Category('ELECTRO');
        $product = new Product('Keyboard', new Money(34999, 'PLN'), [$category]);
        $product->releaseEvents();

        $product->rename('Keyboard');
        $product->changePrice(new Money(34999, 'PLN'));
        $product->replaceCategories([$category]);

        self::assertSame([], $product->releaseEvents());
    }

    #[Test]
    public function itDeduplicatesCategories(): void
    {
        $category = new Category('ELECTRO');

        $product = new Product('Keyboard', new Money(100, 'PLN'), [$category, $category]);

        self::assertSame(['ELECTRO'], $product->getCategoryCodes());
    }

    #[Test]
    public function itRefreshesUpdatedAtWhenOnlyTheCategoriesChange(): void
    {
        $clock = self::mockTime(new \DateTimeImmutable('2026-01-01 10:00:00'));
        $product = self::product();
        $product->initialiseTimestamps();
        $createdAt = $product->getCreatedAt();

        $clock->sleep(3600);
        $product->replaceCategories([new Category('GAMING')]);

        self::assertEquals($createdAt, $product->getCreatedAt());
        self::assertGreaterThan($createdAt, $product->getUpdatedAt());
    }

    #[Test]
    public function itRejectsABlankName(): void
    {
        $this->expectException(InvalidProductName::class);

        new Product('   ', new Money(100, 'PLN'), [new Category('ELECTRO')]);
    }

    #[Test]
    public function itRejectsANameThatDoesNotFitTheStorage(): void
    {
        $product = self::product();
        $product->rename(str_repeat('ż', Product::NAME_MAX_LENGTH));
        $product->releaseEvents();

        try {
            $product->rename(str_repeat('ż', Product::NAME_MAX_LENGTH + 1));
            self::fail('A name longer than the column must be rejected.');
        } catch (InvalidProductName) {
            self::assertSame(str_repeat('ż', Product::NAME_MAX_LENGTH), $product->getName());
            self::assertSame([], $product->releaseEvents(), 'A rejected rename must not record an event.');
        }
    }

    #[Test]
    public function itStampsTheSaveEventWithTheApplicationClock(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-03-01 12:00:00'));

        $events = self::product()->releaseEvents();

        self::assertInstanceOf(ProductSaved::class, $events[0]);
        self::assertEquals(new \DateTimeImmutable('2026-03-01 12:00:00'), $events[0]->occurredAt());
    }

    private static function product(): Product
    {
        return new Product('Keyboard', new Money(34999, 'PLN'), [new Category('ELECTRO')]);
    }
}
