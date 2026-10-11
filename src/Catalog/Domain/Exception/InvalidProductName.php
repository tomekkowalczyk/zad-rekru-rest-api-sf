<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidProductName extends \InvalidArgumentException implements DomainException
{
    public static function blank(): self
    {
        return new self('Product name cannot be blank.');
    }

    public static function tooLong(string $name, int $maxLength): self
    {
        return new self(\sprintf(
            'Product name is %d characters long, the maximum is %d.',
            mb_strlen($name),
            $maxLength,
        ));
    }
}
