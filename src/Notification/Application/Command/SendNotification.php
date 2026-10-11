<?php

declare(strict_types=1);

namespace App\Notification\Application\Command;

use App\Notification\Domain\Notification;

/**
 * Queued unit of work: deliver one notification on exactly one channel.
 *
 * Fanning out *before* the queue (one message per channel instead of one
 * message for all of them) means a failing e-mail server never re-triggers the
 * operation log, and each channel gets its own retry budget.
 */
final readonly class SendNotification
{
    public function __construct(
        public Notification $notification,
        public string $channel,
    ) {
    }
}
