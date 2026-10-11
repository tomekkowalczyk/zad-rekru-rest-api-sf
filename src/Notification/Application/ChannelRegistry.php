<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Notification\Domain\Exception\UnknownNotificationChannel;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationChannel;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Lookup table of all channels registered through the
 * `app.notification_channel` tag.
 */
final class ChannelRegistry
{
    /** @var array<string, NotificationChannel>|null */
    private ?array $indexed = null;

    /**
     * @param iterable<NotificationChannel> $channels
     */
    public function __construct(
        #[AutowireIterator(NotificationChannel::TAG)]
        private readonly iterable $channels,
    ) {
    }

    /**
     * @return list<NotificationChannel>
     */
    public function supporting(Notification $notification): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (NotificationChannel $channel): bool => $channel->supports($notification),
        ));
    }

    /**
     * @throws UnknownNotificationChannel
     */
    public function get(string $name): NotificationChannel
    {
        return $this->all()[$name] ?? throw UnknownNotificationChannel::named($name, $this->names());
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array<string, NotificationChannel>
     */
    private function all(): array
    {
        if (null !== $this->indexed) {
            return $this->indexed;
        }

        $indexed = [];
        foreach ($this->channels as $channel) {
            $indexed[$channel->name()] = $channel;
        }

        return $this->indexed = $indexed;
    }
}
