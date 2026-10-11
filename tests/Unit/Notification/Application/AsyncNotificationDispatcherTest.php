<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application;

use App\Notification\Application\AsyncNotificationDispatcher;
use App\Notification\Application\ChannelRegistry;
use App\Notification\Application\Command\SendNotification;
use App\Notification\Domain\Notification;
use App\Tests\Unit\Notification\RecordingChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(AsyncNotificationDispatcher::class)]
#[CoversClass(ChannelRegistry::class)]
final class AsyncNotificationDispatcherTest extends TestCase
{
    #[Test]
    public function itQueuesOneMessagePerSupportingChannel(): void
    {
        $notification = self::notification();
        $dispatched = [];

        $dispatcher = new AsyncNotificationDispatcher(
            new ChannelRegistry([
                new RecordingChannel('operation_log'),
                new RecordingChannel('email'),
                new RecordingChannel('sms', supports: false),
            ]),
            self::bus($dispatched),
            new NullLogger(),
        );

        $dispatcher->dispatch($notification);

        self::assertCount(2, $dispatched, 'Only supporting channels must be queued.');
        self::assertSame(['operation_log', 'email'], array_map(
            static fn (SendNotification $command): string => $command->channel,
            $dispatched,
        ));
        self::assertSame($notification, $dispatched[0]->notification);
    }

    #[Test]
    public function itDoesNotDeliverSynchronously(): void
    {
        $channel = new RecordingChannel('email');
        $dispatched = [];

        $dispatcher = new AsyncNotificationDispatcher(
            new ChannelRegistry([$channel]),
            self::bus($dispatched),
            new NullLogger(),
        );

        $dispatcher->dispatch(self::notification());

        self::assertSame([], $channel->sent, 'Delivery must happen in the worker, not in the request.');
    }

    #[Test]
    public function itWarnsAndQueuesNothingWhenNoChannelSupportsTheNotification(): void
    {
        $dispatched = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('no channel'), [
                'type' => 'product.saved',
                'available_channels' => ['email'],
            ]);

        $dispatcher = new AsyncNotificationDispatcher(
            new ChannelRegistry([new RecordingChannel('email', supports: false)]),
            self::bus($dispatched),
            $logger,
        );

        $dispatcher->dispatch(self::notification());

        self::assertSame([], $dispatched);
    }

    /**
     * Proves the extension point: a brand new channel is picked up without a
     * single change to the dispatcher.
     */
    #[Test]
    public function itPicksUpNewlyRegisteredChannelsAutomatically(): void
    {
        $dispatched = [];

        $dispatcher = new AsyncNotificationDispatcher(
            new ChannelRegistry([new RecordingChannel('operation_log'), new RecordingChannel('slack')]),
            self::bus($dispatched),
            new NullLogger(),
        );

        $dispatcher->dispatch(self::notification());

        self::assertContains('slack', array_map(
            static fn (SendNotification $command): string => $command->channel,
            $dispatched,
        ));
    }

    private static function notification(): Notification
    {
        return new Notification(
            type: 'product.saved',
            subject: 'Product created: Keyboard',
            body: 'Product "Keyboard" has been created.',
            recipients: ['ops@example.com'],
            context: ['product_id' => '0190f0d4-1e2b-7000-8000-000000000000'],
        );
    }

    /**
     * @param list<SendNotification> $dispatched
     */
    private static function bus(array &$dispatched): MessageBusInterface
    {
        return new class($dispatched) implements MessageBusInterface {
            /**
             * @param list<SendNotification> $dispatched
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

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                \assert($message instanceof SendNotification);
                $this->dispatched[] = $message;

                return new Envelope($message, $stamps);
            }
        };
    }
}
