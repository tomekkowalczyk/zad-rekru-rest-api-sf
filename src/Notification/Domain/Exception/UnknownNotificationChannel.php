<?php

declare(strict_types=1);

namespace App\Notification\Domain\Exception;

final class UnknownNotificationChannel extends \InvalidArgumentException
{
    /**
     * @param list<string> $available
     */
    public static function named(string $name, array $available): self
    {
        return new self(\sprintf(
            'Notification channel "%s" is not registered. Available channels: %s.',
            $name,
            [] === $available ? '<none>' : implode(', ', $available),
        ));
    }
}
