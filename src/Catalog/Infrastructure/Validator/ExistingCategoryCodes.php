<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Every referenced category code must already exist, otherwise the client gets
 * a precise 422 pointing at the unknown codes.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ExistingCategoryCodes extends Constraint
{
    public string $message = 'Unknown category code(s): {{ codes }}.';
}
