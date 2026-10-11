<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Guarantees a 422 (instead of a 500 from the unique index) when a category
 * code is already taken. Applied to the resource, because the DTO - not the
 * entity - is what the serialiser validates.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UniqueCategoryCode extends Constraint
{
    public string $message = 'A category with code "{{ code }}" already exists.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
