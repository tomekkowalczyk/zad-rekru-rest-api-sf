<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Notification\Domain\Exception\NotificationDeliveryFailed;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationChannel;

/**
 * In-memory channel used across the notification test suite.
 */
final class RecordingChannel implements NotificationChannel
{
    /** @var list<Notification> */
    public array $sent = [];

    public function __construct(
        private readonly string $name,
        private readonly bool $supports = true,
        private readonly ?\Throwable $failWith = null,
    ) {
    }

    public function name(): string
    {
        \assert('' !== $this->name);

        return $this->name;
    }

    public function supports(Notification $notification): bool
    {
        return $this->supports;
    }

    public function send(Notification $notification): void
    {
        if ($this->failWith instanceof \Throwable) {
            throw NotificationDeliveryFailed::onChannel($this->name(), $notification->type, $this->failWith);
        }

        $this->sent[] = $notification;
    }
}
