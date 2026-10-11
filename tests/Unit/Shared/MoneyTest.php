<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared;

use App\Shared\Domain\Exception\InvalidMoney;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Money::class)]
final class MoneyTest extends TestCase
{
    #[Test]
    public function itStoresMinorUnits(): void
    {
        $money = new Money(34999, 'pln');

        self::assertSame(34999, $money->getAmount());
        self::assertSame('PLN', $money->getCurrency(), 'Currency codes are normalised to upper case.');
        self::assertSame('349.99 PLN', (string) $money);
    }

    #[Test]
    public function itRejectsNegativeAmounts(): void
    {
        $this->expectException(InvalidMoney::class);

        new Money(-1, 'PLN');
    }

    #[Test]
    public function itRejectsAmountsThatDoNotFitTheStorage(): void
    {
        self::assertSame(Money::MAX_AMOUNT, new Money(Money::MAX_AMOUNT, 'PLN')->getAmount());

        $this->expectException(InvalidMoney::class);

        new Money(Money::MAX_AMOUNT + 1, 'PLN');
    }

    #[Test]
    public function itRejectsMalformedCurrencyCodes(): void
    {
        $this->expectException(InvalidMoney::class);

        new Money(100, 'PLNN');
    }

    #[Test]
    public function itRejectsUnknownCurrencies(): void
    {
        $this->expectException(InvalidMoney::class);

        new Money(100, 'XYZ');
    }

    #[Test]
    public function itFormatsWithTheCurrencyMinorUnitExponent(): void
    {
        self::assertSame('5.05 PLN', (string) new Money(505, 'PLN'));
        self::assertSame('0.00 EUR', (string) new Money(0, 'EUR'));
        self::assertSame('1500 JPY', (string) new Money(1500, 'JPY'));
        self::assertSame('1.005 BHD', (string) new Money(1005, 'BHD'));
        self::assertSame('42949672.95 USD', (string) new Money(Money::MAX_AMOUNT, 'USD'));
    }

    #[Test]
    public function itComparesByValue(): void
    {
        self::assertTrue(new Money(100, 'PLN')->equals(new Money(100, 'PLN')));
        self::assertFalse(new Money(100, 'PLN')->equals(new Money(100, 'EUR')));
        self::assertFalse(new Money(100, 'PLN')->equals(new Money(101, 'PLN')));
    }
}
