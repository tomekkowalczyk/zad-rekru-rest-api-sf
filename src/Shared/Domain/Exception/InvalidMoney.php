<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class InvalidMoney extends \InvalidArgumentException implements DomainException
{
    public static function negativeAmount(int $amount): self
    {
        return new self(\sprintf('Price cannot be negative, got "%d".', $amount));
    }

    public static function amountTooLarge(int $amount, int $max): self
    {
        return new self(\sprintf('Price cannot exceed %d minor units, got "%d".', $max, $amount));
    }

    public static function unsupportedCurrency(string $currency): self
    {
        return new self(\sprintf('"%s" is not a valid ISO 4217 currency code.', $currency));
    }
}
