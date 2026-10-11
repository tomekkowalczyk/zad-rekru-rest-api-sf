<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Doctrine\Entity;

use App\Notification\Domain\Notification;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Uid\Uuid;

/**
 * Durable, queryable audit trail of every notified business operation.
 *
 * Lives in the infrastructure layer on purpose: it is a persistence detail of
 * the operation-log channel, not a domain concept.
 */
#[ORM\Entity]
#[ORM\Table(name: 'operation_log')]
#[ORM\Index(name: 'idx_operation_log_type', columns: ['type'])]
#[ORM\Index(name: 'idx_operation_log_logged_at', columns: ['logged_at'])]
class OperationLog
{
    public const int SUBJECT_MAX_LENGTH = 255;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $loggedAt;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(#[ORM\Column(type: 'string', length: 100)]
        private string $type, #[ORM\Column(type: 'string', length: self::SUBJECT_MAX_LENGTH)]
        private string $subject, #[ORM\Column(type: 'text')]
        private string $body, #[ORM\Column(type: 'json')]
        private array $context)
    {
        $this->id = Uuid::v7();
        $this->loggedAt = Clock::get()->now();
        // Subjects embed user input (e.g. a 255 character product name); a
        // too-long one must not turn the audit entry into a poison message.
        // The full text is still kept in the body.
        if (mb_strlen($this->subject) > self::SUBJECT_MAX_LENGTH) {
            $this->subject = mb_substr($this->subject, 0, self::SUBJECT_MAX_LENGTH - 1).'…';
        }
    }

    public static function fromNotification(Notification $notification): self
    {
        return new self(
            $notification->type,
            $notification->subject,
            $notification->body,
            $notification->context,
        );
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getLoggedAt(): \DateTimeImmutable
    {
        return $this->loggedAt;
    }
}
