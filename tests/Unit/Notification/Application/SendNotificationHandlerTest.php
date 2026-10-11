<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Application;

use App\Notification\Application\ChannelRegistry;
use App\Notification\Application\Command\SendNotification;
use App\Notification\Application\Handler\SendNotificationHandler;
use App\Notification\Domain\Exception\NotificationDeliveryFailed;
use App\Notification\Domain\Exception\UnknownNotificationChannel;
use App\Notification\Domain\Notification;
use App\Tests\Unit\Notification\RecordingChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(SendNotificationHandler::class)]
final class SendNotificationHandlerTest extends TestCase
{
    #[Test]
    public function itDeliversOnTheRequestedChannelOnly(): void
    {
        $email = new RecordingChannel('email');
        $log = new RecordingChannel('operation_log');
        $notification = self::notification();

        $handler = new SendNotificationHandler(new ChannelRegistry([$email, $log]), new NullLogger());
        $handler(new SendNotification($notification, 'email'));

        self::assertSame([$notification], $email->sent);
        self::assertSame([], $log->sent, 'Channels are isolated from each other.');
    }

    #[Test]
    public function itRecordsEachDeliveryInTheLog(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with('Notification delivered.', ['type' => 'product.saved', 'channel' => 'email']);

        $handler = new SendNotificationHandler(new ChannelRegistry([new RecordingChannel('email')]), $logger);
        $handler(new SendNotification(self::notification(), 'email'));
    }

    /**
     * Failures must bubble up: that is what makes Messenger retry the message
     * and eventually move it to the failure transport.
     */
    #[Test]
    public function itLetsDeliveryFailuresBubbleUpForRetry(): void
    {
        $handler = new SendNotificationHandler(
            new ChannelRegistry([new RecordingChannel('email', failWith: new \RuntimeException('SMTP down'))]),
            new NullLogger(),
        );

        $this->expectException(NotificationDeliveryFailed::class);
        $this->expectExceptionMessageMatches('/SMTP down/');

        $handler(new SendNotification(self::notification(), 'email'));
    }

    #[Test]
    public function itFailsLoudlyOnAnUnknownChannel(): void
    {
        $handler = new SendNotificationHandler(
            new ChannelRegistry([new RecordingChannel('email')]),
            new NullLogger(),
        );

        $this->expectException(UnknownNotificationChannel::class);
        $this->expectExceptionMessageIsOrContains('"carrier-pigeon" is not registered');

        $handler(new SendNotification(self::notification(), 'carrier-pigeon'));
    }

    private static function notification(): Notification
    {
        return new Notification('product.saved', 'Subject', 'Body', ['ops@example.com']);
    }
}
