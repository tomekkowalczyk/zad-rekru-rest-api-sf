<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Channel;

use App\Notification\Domain\Exception\NotificationDeliveryFailed;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationChannel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends the notification by e-mail.
 *
 * The transport is configured through `MAILER_DSN`; in DDEV it points at
 * Mailpit, so messages are really sent and inspectable without leaving the
 * development machine.
 */
final readonly class EmailChannel implements NotificationChannel
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(param: 'app.notification.sender_email')]
        private string $senderEmail,
        #[Autowire(param: 'app.notification.sender_name')]
        private string $senderName,
    ) {
    }

    public function name(): string
    {
        return 'email';
    }

    public function supports(Notification $notification): bool
    {
        return $notification->hasRecipients();
    }

    public function send(Notification $notification): void
    {
        $email = new Email()
            ->from(new Address($this->senderEmail, $this->senderName))
            ->to(...$notification->recipients)
            ->subject($notification->subject)
            ->text($notification->body);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            throw NotificationDeliveryFailed::onChannel($this->name(), $notification->type, $exception);
        }
    }
}
