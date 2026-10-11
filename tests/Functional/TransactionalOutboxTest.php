<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Notification\Domain\NotificationDispatcher;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Support\ObservableNotificationDispatcher;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * The product row and its queued notifications must share one transaction:
 * neither may be committed without the other (ADR-0003).
 */
final class TransactionalOutboxTest extends KernelTestCase
{
    private Connection $connection;

    private ObservableNotificationDispatcher $dispatcher;

    private ProductRepository $products;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->connection = $container->get(Connection::class);
        // The test environment decorates the real dispatcher (services_test.yaml).
        $dispatcher = $container->get(NotificationDispatcher::class);
        self::assertInstanceOf(ObservableNotificationDispatcher::class, $dispatcher);
        $this->dispatcher = $dispatcher;
        $this->products = $container->get(ProductRepository::class);
        $this->dispatcher->reset();
    }

    #[Test]
    public function notificationsAreQueuedInsideTheSaveTransaction(): void
    {
        $product = new Product('Outbox lamp', new Money(1999, 'PLN'), [$this->category()]);
        $levelOutsideSave = $this->connection->getTransactionNestingLevel();

        $this->products->save($product);

        self::assertNotSame([], $this->dispatcher->nestingLevels());

        foreach ($this->dispatcher->nestingLevels() as $level) {
            self::assertGreaterThan($levelOutsideSave, $level);
        }
    }

    #[Test]
    public function aProductIsNotStoredWhenItsNotificationCannotBeQueued(): void
    {
        $product = new Product('Outbox desk', new Money(54900, 'PLN'), [$this->category()]);
        $this->dispatcher->failNextDispatchWith(new \RuntimeException('Queue unavailable'));

        try {
            $this->products->save($product);
            self::fail('The save should have failed together with the queue insert.');
        } catch (HandlerFailedException $exception) {
            self::assertStringContainsString('Queue unavailable', $exception->getMessage());
        }

        self::assertFalse(
            $this->connection->fetchOne('SELECT 1 FROM product WHERE id = ?', [$product->getId()->toBinary()]),
        );
    }

    private function category(): Category
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $category = new Category('OUTBOX');
        $entityManager->persist($category);
        $entityManager->flush();

        return $category;
    }
}
