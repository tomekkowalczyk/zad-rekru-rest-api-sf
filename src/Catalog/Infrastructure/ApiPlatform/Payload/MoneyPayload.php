<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Payload;

use ApiPlatform\Metadata\ApiProperty;
use App\Shared\Domain\ValueObject\Money;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Transport representation of {@see Money}.
 *
 * The value object itself stays free of serialisation and validation metadata.
 */
final class MoneyPayload
{
    #[ApiProperty(description: 'Amount expressed in minor units (e.g. 1999 = 19.99).', example: 1999)]
    #[Groups(['product:read', 'product:write'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(Money::MAX_AMOUNT)]
    public ?int $amount = null;

    #[ApiProperty(description: 'ISO 4217 alphabetic currency code.', example: 'PLN')]
    #[Groups(['product:read', 'product:write'])]
    #[Assert\NotBlank]
    #[Assert\Currency]
    public ?string $currency = null;

    public static function fromMoney(Money $money): self
    {
        $payload = new self();
        $payload->amount = $money->getAmount();
        $payload->currency = $money->getCurrency();

        return $payload;
    }

    public function toMoney(): Money
    {
        return new Money($this->amount ?? 0, $this->currency ?? '');
    }
}
