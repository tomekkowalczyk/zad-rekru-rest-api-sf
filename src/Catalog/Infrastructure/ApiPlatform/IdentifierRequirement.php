<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform;

/**
 * Route-level guard for identifiers: a malformed one is a 404 from the router
 * instead of a 500 from the Doctrine UUID type.
 */
final class IdentifierRequirement
{
    // Case-insensitive on purpose: Symfony\Component\Uid\Uuid::fromString()
    // accepts upper case, so rejecting it would break existing clients.
    public const string UUID = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
}
