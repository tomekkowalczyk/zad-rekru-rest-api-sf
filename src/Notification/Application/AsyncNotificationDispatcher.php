<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Application\Command\SendNotification;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationDispatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fans a notification out to every supporting channel by putting one message
 * per channel on the (database backed) queue.
 */
final readonly class AsyncNotificationDispatcher implements NotificationDispatcher
{
    public function __construct(
        private ChannelRegistry $channels,
        private MessageBusInterface $commandBus,
        private LoggerInterface $logger,
    ) {
    }

    public function dispatch(Notification $notification): void
    {
        $channels = $this->channels->supporting($notification);

        if ([] === $channels) {
            $this->logger->warning('Notification dropped: no channel supports it.', [
                'type' => $notification->type,
                'available_channels' => $this->channels->names(),
            ]);

            return;
        }

        foreach ($channels as $channel) {
            $this->commandBus->dispatch(new SendNotification($notification, $channel->name()));
        }
    }
}
