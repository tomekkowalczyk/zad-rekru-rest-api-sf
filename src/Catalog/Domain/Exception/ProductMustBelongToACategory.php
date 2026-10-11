<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class ProductMustBelongToACategory extends \DomainException implements DomainException
{
    public static function create(): self
    {
        return new self('A product must belong to at least one category.');
    }
}
