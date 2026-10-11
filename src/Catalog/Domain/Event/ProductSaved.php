<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * Raised once per transaction in which a product (and its category links) were
 * written.
 *
 * Carries identifiers only: consumers re-read the aggregate, which guarantees
 * they always observe the committed state instead of a stale snapshot.
 */
final readonly class ProductSaved implements DomainEvent
{
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        public Uuid $productId,
        public bool $wasCreated,
        ?\DateTimeImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? Clock::get()->now();
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
