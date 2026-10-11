<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Removing the category would leave products without one, breaking the
 * "a product belongs to at least one category" invariant.
 */
final class CategoryStillInUse extends \DomainException implements DomainException
{
    public static function withCode(string $code, ?\Throwable $previous = null): self
    {
        return new self(
            \sprintf('Category "%s" is still assigned to products and cannot be deleted.', $code),
            previous: $previous,
        );
    }
}
