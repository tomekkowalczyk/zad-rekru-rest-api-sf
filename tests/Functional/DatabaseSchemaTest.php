<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The test database is built from the migrations, so this fails both when a
 * mapping changes without a migration and when a migration drifts from the
 * mapping — the same check as `doctrine:schema:validate`, but inside the suite.
 */
final class DatabaseSchemaTest extends KernelTestCase
{
    /**
     * Tables that exist without an entity: the migrations bookkeeping and the
     * Messenger queue (swapped for `in-memory://` here, so its schema listener
     * is not registered to claim the table).
     */
    private const array UNMAPPED_TABLES = ['doctrine_migration_versions', 'messenger_messages'];

    #[Test]
    public function theMigratedSchemaMatchesTheMapping(): void
    {
        self::bootKernel();
        $validator = new SchemaValidator(self::getContainer()->get(EntityManagerInterface::class));

        self::assertSame([], $validator->validateMapping());
        $differences = array_filter(
            $validator->getUpdateSchemaList(),
            static fn (string $sql): bool => !\in_array(
                preg_replace('/^DROP TABLE (\w+)$/', '$1', $sql),
                self::UNMAPPED_TABLES,
                true,
            ),
        );

        self::assertSame([], array_values($differences));
    }
}
