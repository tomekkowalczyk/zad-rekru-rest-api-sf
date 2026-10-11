<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidMoney;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Intl\Currencies;

/**
 * Immutable amount of money stored in the *minor* unit (e.g. grosze, cents).
 *
 * Floats are deliberately avoided: `0.1 + 0.2 !== 0.3` has no place in a price.
 */
#[ORM\Embeddable]
final class Money implements \Stringable
{
    /**
     * Upper bound of the unsigned INT column backing the amount. Enforced here so
     * an oversized price is a business error, not a database crash.
     */
    public const int MAX_AMOUNT = 4_294_967_295;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'comment' => 'Amount in minor units'])]
    private int $amount;

    #[ORM\Column(type: 'string', length: 3, options: ['comment' => 'ISO 4217 currency code'])]
    private string $currency;

    /**
     * @param int    $amount   amount in minor units, must not be negative
     * @param string $currency ISO 4217 alphabetic code
     *
     * @throws InvalidMoney
     */
    public function __construct(int $amount, string $currency)
    {
        $currency = strtoupper($currency);

        if ($amount < 0) {
            throw InvalidMoney::negativeAmount($amount);
        }

        if ($amount > self::MAX_AMOUNT) {
            throw InvalidMoney::amountTooLarge($amount, self::MAX_AMOUNT);
        }

        // The API validates the same way; repeating it here keeps fixtures,
        // commands and future callers from persisting an unknown currency.
        if (!Currencies::exists($currency)) {
            throw InvalidMoney::unsupportedCurrency($currency);
        }

        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function __toString(): string
    {
        // Integer arithmetic only, and the minor-unit exponent depends on the
        // currency: JPY has none, BHD has three.
        $digits = Currencies::getFractionDigits($this->currency);
        if (0 === $digits) {
            return \sprintf('%d %s', $this->amount, $this->currency);
        }

        $divisor = 10 ** $digits;

        return \sprintf(
            '%d.%s %s',
            intdiv($this->amount, $divisor),
            str_pad((string) ($this->amount % $divisor), $digits, '0', \STR_PAD_LEFT),
            $this->currency,
        );
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
