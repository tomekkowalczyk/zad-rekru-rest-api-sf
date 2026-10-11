<?php

declare(strict_types=1);

namespace App\Notification\Application\Handler;

use App\Notification\Application\ChannelRegistry;
use App\Notification\Application\Command\SendNotification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Executed by the worker process. Any exception thrown here is intentional:
 * Messenger retries the message and finally moves it to the failure transport.
 */
#[AsMessageHandler]
final readonly class SendNotificationHandler
{
    public function __construct(
        private ChannelRegistry $channels,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendNotification $command): void
    {
        $channel = $this->channels->get($command->channel);
        $notification = $command->notification;

        $channel->send($notification);

        $this->logger->info('Notification delivered.', [
            'type' => $notification->type,
            'channel' => $channel->name(),
        ]);
    }
}
