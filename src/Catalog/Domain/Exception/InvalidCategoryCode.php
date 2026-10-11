<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidCategoryCode extends \InvalidArgumentException implements DomainException
{
    public static function empty(): self
    {
        return new self('Category code cannot be empty.');
    }

    public static function tooLong(string $code, int $maxLength): self
    {
        return new self(\sprintf(
            'Category code "%s" is %d characters long, the maximum is %d.',
            $code,
            mb_strlen($code),
            $maxLength,
        ));
    }
}
