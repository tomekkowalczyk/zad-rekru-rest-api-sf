<?php

declare(strict_types=1);

namespace App\Shared\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Clock\Clock;

trait TimestampableTrait
{
    #[ORM\Column(type: 'datetime_immutable', options: ['comment' => 'Creation timestamp (UTC)'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', options: ['comment' => 'Last modification timestamp (UTC)'])]
    private \DateTimeImmutable $updatedAt;

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function initialiseTimestamps(): void
    {
        $now = Clock::get()->now();

        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = Clock::get()->now();
    }

    protected function touch(): void
    {
        $this->updatedAt = Clock::get()->now();
    }
}
