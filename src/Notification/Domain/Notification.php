<?php

declare(strict_types=1);

namespace App\Notification\Domain;

/**
 * Channel-agnostic description of "something worth telling somebody about".
 *
 * It is a plain, immutable value object: no Doctrine entity, no framework
 * dependency, fully serialisable. That is what allows it to be put on a queue
 * and replayed by a worker process.
 */
final readonly class Notification
{
    /**
     * @param non-empty-string                                         $type       stable machine readable key, e.g. `product.saved`
     * @param list<string>                                             $recipients channel-specific addresses (e-mail, phone, Slack channel…)
     * @param array<string, scalar|array<array-key, scalar|null>|null> $context    structured payload for logs and templates
     */
    public function __construct(
        public string $type,
        public string $subject,
        public string $body,
        public array $recipients = [],
        public array $context = [],
    ) {
    }

    public function hasRecipients(): bool
    {
        return [] !== $this->recipients;
    }
}
