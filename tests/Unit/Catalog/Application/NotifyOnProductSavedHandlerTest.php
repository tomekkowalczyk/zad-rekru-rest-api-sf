<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Application;

use App\Catalog\Application\Handler\NotifyOnProductSavedHandler;
use App\Catalog\Application\Notification\ProductSavedNotificationFactory;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Event\ProductSaved;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationDispatcher;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;

#[CoversClass(NotifyOnProductSavedHandler::class)]
final class NotifyOnProductSavedHandlerTest extends TestCase
{
    #[Test]
    public function itDispatchesANotificationForTheSavedProduct(): void
    {
        $product = new Product('Keyboard', new Money(34999, 'PLN'), [new Category('ELECTRO')]);
        $product->initialiseTimestamps();

        $dispatched = [];
        $handler = new NotifyOnProductSavedHandler(
            self::repositoryReturning($product),
            new ProductSavedNotificationFactory(['ops@example.com']),
            self::dispatcherCollecting($dispatched),
            new NullLogger(),
        );

        $handler(new ProductSaved($product->getId(), wasCreated: true));

        self::assertCount(1, $dispatched);
        self::assertSame('Product created: Keyboard', $dispatched[0]->subject);
    }

    #[Test]
    public function itSkipsAndWarnsWhenTheProductIsAlreadyGone(): void
    {
        $dispatched = [];
        $productId = Uuid::v7();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::anything(), ['product_id' => $productId->toRfc4122()]);

        $handler = new NotifyOnProductSavedHandler(
            self::repositoryReturning(null),
            new ProductSavedNotificationFactory([]),
            self::dispatcherCollecting($dispatched),
            $logger,
        );

        $handler(new ProductSaved($productId, wasCreated: false));

        self::assertSame([], $dispatched);
    }

    private static function repositoryReturning(?Product $product): ProductRepository
    {
        return new readonly class($product) implements ProductRepository {
            public function __construct(private ?Product $product)
            {
            }

            public function find(Uuid $id): ?Product
            {
                return $this->product;
            }

            public function save(Product $product): void
            {
            }

            public function remove(Product $product): void
            {
            }
        };
    }

    /**
     * @param list<Notification> $dispatched
     */
    private static function dispatcherCollecting(array &$dispatched): NotificationDispatcher
    {
        return new class($dispatched) implements NotificationDispatcher {
            /**
             * @param list<Notification> $dispatched
             */
            public function __construct(private array &$dispatched)
            {
            }

            /**
             * @return list<object>
             */
            public function collected(): array
            {
                return $this->dispatched;
            }

            public function dispatch(Notification $notification): void
            {
                $this->dispatched[] = $notification;
            }
        };
    }
}
