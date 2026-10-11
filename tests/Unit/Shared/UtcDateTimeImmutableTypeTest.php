<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Infrastructure\Doctrine\Type\UtcDateTimeImmutableType;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UtcDateTimeImmutableType::class)]
final class UtcDateTimeImmutableTypeTest extends TestCase
{
    #[Test]
    public function itWritesUtcWhateverTheTimeZoneOfTheValue(): void
    {
        $warsaw = new \DateTimeImmutable('2026-07-01 12:00:00', new \DateTimeZone('Europe/Warsaw'));

        self::assertSame(
            '2026-07-01 10:00:00',
            new UtcDateTimeImmutableType()->convertToDatabaseValue($warsaw, new MySQL80Platform()),
        );
    }

    #[Test]
    public function itReadsTheStoredValueAsUtc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Warsaw');

        try {
            $value = new UtcDateTimeImmutableType()->convertToPHPValue('2026-07-01 10:00:00', new MySQL80Platform());
        } finally {
            date_default_timezone_set($previous);
        }

        self::assertNotNull($value);
        self::assertSame('2026-07-01T10:00:00+00:00', $value->format(\DATE_ATOM));
    }

    #[Test]
    public function itRoundTripsTheSameInstant(): void
    {
        $type = new UtcDateTimeImmutableType();
        $platform = new MySQL80Platform();
        $original = new \DateTimeImmutable('2026-01-15 23:30:00', new \DateTimeZone('America/New_York'));

        $restored = $type->convertToPHPValue($type->convertToDatabaseValue($original, $platform), $platform);

        self::assertNotNull($restored);
        self::assertSame($original->getTimestamp(), $restored->getTimestamp());
    }
}
