<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

/**
 * Marker for every exception raised by a business rule.
 *
 * Lets the HTTP layer translate domain failures into 4xx responses without
 * knowing each concrete exception.
 */
interface DomainException extends \Throwable
{
}
