<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Listener;

use App\Shared\Domain\Entity\AggregateRoot;
use App\Shared\Domain\Event\DomainEvent;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Publishes domain events recorded by aggregates once the flush has written
 * them to the database.
 *
 * `postFlush` is fired by Doctrine after the flush's own `commit()`. When the
 * flush runs inside an outer transaction (repositories use
 * `wrapInTransaction()`), that outer transaction is still open here, so the
 * notification messages queued by the event handlers on the same connection
 * share its fate: a rolled back save never leaves a queued e-mail behind, and
 * a committed save never loses one (transactional outbox, ADR-0003).
 */
#[AsDoctrineListener(event: Events::postFlush)]
final class PublishDomainEventsListener
{
    /**
     * Guards against infinite recursion: an event handler may flush again,
     * which would re-enter this listener.
     */
    private bool $publishing = false;

    public function __construct(private readonly MessageBusInterface $eventBus)
    {
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->publishing) {
            return;
        }

        $this->publishing = true;

        try {
            // A handler may change an aggregate and flush again; that nested
            // flush returns early above, so its events are picked up by the
            // next iteration instead of being lost.
            while ([] !== $events = $this->collect($args)) {
                foreach ($events as $event) {
                    $this->eventBus->dispatch($event);
                }
            }
        } finally {
            $this->publishing = false;
        }
    }

    /**
     * @return list<DomainEvent>
     */
    private function collect(PostFlushEventArgs $args): array
    {
        $events = [];

        foreach ($args->getObjectManager()->getUnitOfWork()->getIdentityMap() as $entities) {
            foreach ($entities as $entity) {
                if ($entity instanceof AggregateRoot) {
                    array_push($events, ...$entity->releaseEvents());
                }
            }
        }

        return $events;
    }
}
