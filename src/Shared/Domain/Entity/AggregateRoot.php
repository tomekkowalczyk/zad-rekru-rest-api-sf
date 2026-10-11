<?php

declare(strict_types=1);

namespace App\Shared\Domain\Entity;

use App\Shared\Domain\Event\DomainEvent;

/**
 * Base class for aggregate roots.
 *
 * Events are recorded in memory while the aggregate changes and are released by
 * the infrastructure *after* the transaction has been committed, so that no
 * side effect (e-mail, operation log, queue message) can ever describe a state
 * that was rolled back.
 */
abstract class AggregateRoot
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @param class-string<DomainEvent> $eventClass
     */
    protected function hasRecorded(string $eventClass): bool
    {
        return array_any($this->recordedEvents, static fn (DomainEvent $event): bool => $event instanceof $eventClass);
    }
}
