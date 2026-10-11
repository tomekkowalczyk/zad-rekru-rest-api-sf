<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application;

use App\Catalog\Application\Notification\ProductSavedNotificationFactory;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProductSavedNotificationFactory::class)]
final class ProductSavedNotificationFactoryTest extends TestCase
{
    #[Test]
    public function itDescribesACreatedProduct(): void
    {
        $product = self::product();
        $product->initialiseTimestamps();

        $notification = new ProductSavedNotificationFactory(['ops@example.com'])->create($product, wasCreated: true);

        self::assertSame('product.saved', $notification->type);
        self::assertSame('Product created: Keyboard', $notification->subject);
        self::assertSame(['ops@example.com'], $notification->recipients);
        self::assertStringContainsString('ELECTRO, GAMING', $notification->body);
        self::assertStringContainsString('349.99 PLN', $notification->body);
        self::assertSame(
            [
                'product_id' => $product->getId()->toRfc4122(),
                'product_name' => 'Keyboard',
                'price_amount' => 34999,
                'price_currency' => 'PLN',
                'categories' => ['ELECTRO', 'GAMING'],
                'was_created' => true,
            ],
            $notification->context,
        );
    }

    #[Test]
    public function itDescribesAnUpdatedProduct(): void
    {
        $product = self::product();
        $product->initialiseTimestamps();

        $notification = new ProductSavedNotificationFactory([])->create($product, wasCreated: false);

        self::assertSame('Product updated: Keyboard', $notification->subject);
        self::assertFalse($notification->hasRecipients());
        self::assertFalse($notification->context['was_created']);
    }

    private static function product(): Product
    {
        return new Product(
            'Keyboard',
            new Money(34999, 'PLN'),
            [new Category('ELECTRO'), new Category('GAMING')],
        );
    }
}
