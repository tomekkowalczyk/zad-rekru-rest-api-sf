<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Infrastructure;

use App\Notification\Domain\Exception\NotificationDeliveryFailed;
use App\Notification\Domain\Notification;
use App\Notification\Infrastructure\Channel\EmailChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

#[CoversClass(EmailChannel::class)]
final class EmailChannelTest extends TestCase
{
    #[Test]
    public function itBuildsTheMessageFromTheNotification(): void
    {
        $sent = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (RawMessage $message) use (&$sent): void {
                $sent = $message;
            });

        $channel = new EmailChannel($mailer, 'no-reply@catalog.test', 'Catalog API');
        $channel->send(new Notification(
            type: 'product.saved',
            subject: 'Product created: Keyboard',
            body: 'Details…',
            recipients: ['ops@example.com', 'shop@example.com'],
        ));

        self::assertInstanceOf(Email::class, $sent);
        self::assertSame('Product created: Keyboard', $sent->getSubject());
        self::assertSame('Details…', $sent->getTextBody());
        self::assertSame('no-reply@catalog.test', $sent->getFrom()[0]->getAddress());
        self::assertSame('Catalog API', $sent->getFrom()[0]->getName());
        self::assertSame(
            ['ops@example.com', 'shop@example.com'],
            array_map(static fn (Address $address): string => $address->getAddress(), $sent->getTo()),
        );
    }

    #[Test]
    public function itOptsOutWhenThereIsNobodyToNotify(): void
    {
        $channel = new EmailChannel(self::createStub(MailerInterface::class), 'no-reply@catalog.test', 'Catalog API');

        self::assertFalse($channel->supports(new Notification('product.saved', 'S', 'B')));
        self::assertTrue($channel->supports(new Notification('product.saved', 'S', 'B', ['ops@example.com'])));
    }

    #[Test]
    public function itTranslatesTransportFailuresIntoADomainException(): void
    {
        $mailer = self::createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('Connection refused'));

        $channel = new EmailChannel($mailer, 'no-reply@catalog.test', 'Catalog API');

        $this->expectException(NotificationDeliveryFailed::class);
        $this->expectExceptionMessageIsOrContains('channel "email"');

        $channel->send(new Notification('product.saved', 'S', 'B', ['ops@example.com']));
    }

    #[Test]
    public function itExposesAStableName(): void
    {
        $channel = new EmailChannel(self::createStub(MailerInterface::class), 'no-reply@catalog.test', 'Catalog API');

        self::assertSame('email', $channel->name());
    }
}
