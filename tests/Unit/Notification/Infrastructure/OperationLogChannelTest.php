<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure;

use App\Notification\Domain\Exception\NotificationDeliveryFailed;
use App\Notification\Domain\Notification;
use App\Notification\Infrastructure\Channel\OperationLogChannel;
use App\Notification\Infrastructure\Doctrine\Entity\OperationLog;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(OperationLogChannel::class)]
#[CoversClass(OperationLog::class)]
final class OperationLogChannelTest extends TestCase
{
    #[Test]
    public function itPersistsTheOperationAsAnAuditRecord(): void
    {
        $persisted = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted = $entity;
            });
        $entityManager->expects(self::once())->method('flush');

        $channel = new OperationLogChannel($entityManager, new NullLogger());
        $channel->send(new Notification(
            type: 'product.saved',
            subject: 'Product created: Keyboard',
            body: 'Details…',
            recipients: ['ops@example.com'],
            context: ['product_id' => '0190f0d4-1e2b-7000-8000-000000000000', 'categories' => ['ELECTRO']],
        ));

        self::assertInstanceOf(OperationLog::class, $persisted);
        self::assertSame('product.saved', $persisted->getType());
        self::assertSame('Product created: Keyboard', $persisted->getSubject());
        self::assertSame('Details…', $persisted->getBody());
        self::assertSame(['ELECTRO'], $persisted->getContext()['categories']);
    }

    #[Test]
    public function itAlsoWritesToTheDedicatedLogChannel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Product created: Keyboard', [
                'type' => 'product.saved',
                'context' => ['categories' => ['ELECTRO']],
            ]);

        $channel = new OperationLogChannel(self::createStub(EntityManagerInterface::class), $logger);
        $channel->send(new Notification(
            type: 'product.saved',
            subject: 'Product created: Keyboard',
            body: 'Details…',
            context: ['categories' => ['ELECTRO']],
        ));
    }

    #[Test]
    public function itAlwaysAcceptsNotifications(): void
    {
        $channel = new OperationLogChannel(self::createStub(EntityManagerInterface::class), new NullLogger());

        self::assertTrue($channel->supports(new Notification('whatever', 'S', 'B')));
        self::assertSame('operation_log', $channel->name());
    }

    #[Test]
    public function itShortensSubjectsThatDoNotFitTheColumn(): void
    {
        $subject = 'Product created: '.str_repeat('ż', 255);

        $log = OperationLog::fromNotification(new Notification('product.saved', $subject, 'Full details'));

        self::assertSame(OperationLog::SUBJECT_MAX_LENGTH, mb_strlen($log->getSubject()));
        self::assertStringStartsWith('Product created: żż', $log->getSubject());
        self::assertStringEndsWith('…', $log->getSubject());
        self::assertSame('Full details', $log->getBody());
    }

    #[Test]
    public function itKeepsSubjectsThatFit(): void
    {
        $subject = str_repeat('a', OperationLog::SUBJECT_MAX_LENGTH);

        self::assertSame($subject, OperationLog::fromNotification(new Notification('t', $subject, 'B'))->getSubject());
    }

    /**
     * The limit is a column length in characters, not bytes: a multibyte
     * subject that fits must not be cut short.
     */
    #[Test]
    public function itMeasuresSubjectsInCharacters(): void
    {
        $subject = str_repeat('ż', OperationLog::SUBJECT_MAX_LENGTH);

        self::assertSame($subject, OperationLog::fromNotification(new Notification('t', $subject, 'B'))->getSubject());
    }

    #[Test]
    public function itTranslatesPersistenceFailuresIntoADomainException(): void
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException(new \RuntimeException('Deadlock found'));

        $channel = new OperationLogChannel($entityManager, new NullLogger());

        $this->expectException(NotificationDeliveryFailed::class);
        $this->expectExceptionMessageIsOrContains('channel "operation_log"');

        $channel->send(new Notification('product.saved', 'S', 'B'));
    }
}
