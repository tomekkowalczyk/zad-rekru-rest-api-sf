<?php

declare(strict_types=1);

namespace App\Notification\Domain;

/**
 * Entry point used by other modules. Kept in the domain layer so that callers
 * (e.g. the Catalog module) never depend on the transport implementation.
 */
interface NotificationDispatcher
{
    /**
     * Hands the notification over for delivery on every supporting channel.
     *
     * Implementations are expected to be asynchronous: this call returns before
     * anything is actually delivered.
     */
    public function dispatch(Notification $notification): void;
}
