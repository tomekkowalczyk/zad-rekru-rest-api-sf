<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Channel;

use App\Notification\Domain\Exception\NotificationDeliveryFailed;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationChannel;
use App\Notification\Infrastructure\Doctrine\Entity\OperationLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Writes the operation into a dedicated database table (queryable audit trail)
 * and into the `notification` Monolog channel (operational troubleshooting).
 */
final readonly class OperationLogChannel implements NotificationChannel
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Target('notificationLogger')]
        private LoggerInterface $logger,
    ) {
    }

    public function name(): string
    {
        return 'operation_log';
    }

    public function supports(Notification $notification): bool
    {
        return true;
    }

    public function send(Notification $notification): void
    {
        try {
            $this->entityManager->persist(OperationLog::fromNotification($notification));
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            throw NotificationDeliveryFailed::onChannel($this->name(), $notification->type, $exception);
        }

        $this->logger->info($notification->subject, [
            'type' => $notification->type,
            'context' => $notification->context,
        ]);
    }
}
