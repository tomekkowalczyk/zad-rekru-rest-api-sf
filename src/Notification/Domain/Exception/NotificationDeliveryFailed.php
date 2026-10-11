<?php

declare(strict_types=1);

namespace App\Notification\Domain\Exception;

final class NotificationDeliveryFailed extends \RuntimeException
{
    public static function onChannel(string $channel, string $type, \Throwable $previous): self
    {
        return new self(
            \sprintf('Delivery of notification "%s" failed on channel "%s": %s', $type, $channel, $previous->getMessage()),
            previous: $previous,
        );
    }
}
