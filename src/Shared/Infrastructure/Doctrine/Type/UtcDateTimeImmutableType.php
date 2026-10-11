<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\DateTimeImmutableType;
use Doctrine\DBAL\Types\Exception\InvalidFormat;

/**
 * Replaces the stock `datetime_immutable` type so that every timestamp is
 * written and read as UTC.
 *
 * MySQL `DATETIME` has no time zone. With the stock type the value depended on
 * the PHP default time zone of whichever process wrote it — the web container
 * and the worker disagreed, so the same table mixed local time and UTC.
 */
final class UtcDateTimeImmutableType extends DateTimeImmutableType
{
    private static ?\DateTimeZone $utc = null;

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof \DateTimeImmutable) {
            $value = $value->setTimezone(self::utc());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (null === $value || $value instanceof \DateTimeImmutable) {
            return $value;
        }

        if (!\is_string($value)) {
            return parent::convertToPHPValue($value, $platform);
        }

        $dateTime = \DateTimeImmutable::createFromFormat($platform->getDateTimeFormatString(), $value, self::utc());

        if (false === $dateTime) {
            throw InvalidFormat::new($value, self::class, $platform->getDateTimeFormatString());
        }

        return $dateTime;
    }

    private static function utc(): \DateTimeZone
    {
        return self::$utc ??= new \DateTimeZone('UTC');
    }
}
