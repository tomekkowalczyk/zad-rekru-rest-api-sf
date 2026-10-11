<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationDispatcher;
use Doctrine\DBAL\Connection;

/**
 * Test-only decorator around the real dispatcher. It records the transaction
 * nesting level at dispatch time and can simulate a queue that refuses the
 * insert — the two facts the transactional outbox depends on.
 */
final class ObservableNotificationDispatcher implements NotificationDispatcher
{
    private ?\Throwable $failure = null;

    /** @var list<int> */
    private array $nestingLevels = [];

    public function __construct(
        private readonly NotificationDispatcher $inner,
        private readonly Connection $connection,
    ) {
    }

    public function failNextDispatchWith(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    /**
     * @return list<int>
     */
    public function nestingLevels(): array
    {
        return $this->nestingLevels;
    }

    public function reset(): void
    {
        $this->failure = null;
        $this->nestingLevels = [];
    }

    public function dispatch(Notification $notification): void
    {
        $this->nestingLevels[] = $this->connection->getTransactionNestingLevel();

        if (($failure = $this->failure) instanceof \Throwable) {
            $this->failure = null;

            throw $failure;
        }

        $this->inner->dispatch($notification);
    }
}
