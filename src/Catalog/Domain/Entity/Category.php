<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Exception\InvalidCategoryCode;
use App\Shared\Domain\Entity\TimestampableTrait;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'category')]
#[ORM\HasLifecycleCallbacks]
final class Category
{
    use TimestampableTrait;

    public const int CODE_MAX_LENGTH = 10;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: self::CODE_MAX_LENGTH, unique: true)]
    private string $code;

    /**
     * @throws InvalidCategoryCode
     */
    public function __construct(string $code, ?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->code = self::normaliseCode($code);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @throws InvalidCategoryCode
     */
    public function changeCode(string $code): void
    {
        $code = self::normaliseCode($code);

        if ($code === $this->code) {
            return;
        }

        $this->code = $code;
        $this->touch();
    }

    /**
     * @throws InvalidCategoryCode
     */
    private static function normaliseCode(string $code): string
    {
        $code = trim($code);

        if ('' === $code) {
            throw InvalidCategoryCode::empty();
        }

        if (mb_strlen($code) > self::CODE_MAX_LENGTH) {
            throw InvalidCategoryCode::tooLong($code, self::CODE_MAX_LENGTH);
        }

        return $code;
    }
}
