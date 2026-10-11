<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Marker for facts that already happened inside an aggregate.
 *
 * Implementations MUST be immutable and serializable, because they travel
 * through the message bus and may be persisted in the queue table.
 */
interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
