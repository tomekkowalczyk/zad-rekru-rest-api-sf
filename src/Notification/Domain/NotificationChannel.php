<?php

declare(strict_types=1);

namespace App\Notification\Domain;

/**
 * Extension point of the notification subsystem.
 *
 * Adding Slack, SMS or a webhook means writing one class implementing this
 * interface — nothing else in the application changes, because channels are
 * collected through the `app.notification_channel` tag (applied to every
 * implementation by `_instanceof` in `config/services.yaml`, so the domain
 * contract stays free of container attributes).
 */
interface NotificationChannel
{
    public const string TAG = 'app.notification_channel';

    /**
     * Stable identifier used for routing, logging and queue messages.
     *
     * @return non-empty-string
     */
    public function name(): string;

    /**
     * Lets a channel opt out of a notification (missing recipients, feature
     * flag, notification type it does not care about…).
     */
    public function supports(Notification $notification): bool;

    /**
     * MUST throw on failure so the worker can retry the message.
     *
     * @throws Exception\NotificationDeliveryFailed
     */
    public function send(Notification $notification): void;
}
