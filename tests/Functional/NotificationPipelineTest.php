<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Notification\Application\Command\SendNotification;
use App\Notification\Infrastructure\Doctrine\Entity\OperationLog;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Covers the wiring that unit tests cannot: Doctrine `postFlush` -> domain event
 * -> notification fan-out -> database queue -> worker -> channels.
 */
final class NotificationPipelineTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->transport()->reset();
    }

    #[Test]
    public function savingAProductQueuesOneMessagePerChannel(): void
    {
        $this->persistProduct('Mechanical keyboard');

        $messages = $this->queuedMessages();

        self::assertNotSame([], $messages);
        self::assertEqualsCanonicalizing(
            ['operation_log', 'email'],
            array_map(static fn (SendNotification $message): string => $message->channel, $messages),
        );

        $notification = $messages[0]->notification;
        self::assertSame('product.saved', $notification->type);
        self::assertSame('Product created: Mechanical keyboard', $notification->subject);
        self::assertSame(['TESTCAT'], $notification->context['categories']);
    }

    #[Test]
    public function nothingIsDeliveredDuringTheRequest(): void
    {
        $this->persistProduct('Ergonomic chair');

        self::assertSame(
            0,
            (int) $this->entityManager->createQuery(
                \sprintf('SELECT COUNT(o.id) FROM %s o', OperationLog::class),
            )->getSingleScalarResult(),
            'Channels must only run in the worker process.',
        );
    }

    #[Test]
    public function updatingAProductQueuesASingleUpdateNotification(): void
    {
        $product = $this->persistProduct('27 inch monitor');
        $this->transport()->reset();

        $product->rename('27" monitor');
        $product->changePrice(new Money(189900, 'PLN'));
        $this->entityManager->flush();

        $messages = $this->queuedMessages();

        self::assertCount(2, $messages, 'Two channels, one notification each.');
        self::assertSame('Product updated: 27" monitor', $messages[0]->notification->subject);
        self::assertFalse($messages[0]->notification->context['was_created']);
    }

    /**
     * @return list<SendNotification>
     */
    private function queuedMessages(): array
    {
        $messages = [];

        foreach ($this->transport()->getSent() as $envelope) {
            $message = $envelope->getMessage();
            self::assertInstanceOf(SendNotification::class, $message);
            $messages[] = $message;
        }

        return $messages;
    }

    private function persistProduct(string $name): Product
    {
        $category = $this->entityManager->getRepository(Category::class)->findOneBy(['code' => 'TESTCAT'])
            ?? new Category('TESTCAT');
        $this->entityManager->persist($category);

        $product = new Product($name, new Money(34999, 'PLN'), [$category]);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function transport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport, 'The test environment must use the in-memory transport.');

        return $transport;
    }
}
