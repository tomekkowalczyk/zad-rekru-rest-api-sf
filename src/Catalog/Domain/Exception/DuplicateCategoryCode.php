<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Raised when the unique index wins a race the validator could not see.
 */
final class DuplicateCategoryCode extends \DomainException implements DomainException
{
    public static function withCode(string $code, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('A category with code "%s" already exists.', $code), previous: $previous);
    }
}
