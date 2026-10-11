<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\InvalidCategoryCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Category::class)]
final class CategoryTest extends TestCase
{
    #[Test]
    public function itTrimsTheCode(): void
    {
        self::assertSame('ELECTRO', new Category('  ELECTRO ')->getCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield '11 characters' => ['ABCDEFGHIJK'];
        yield '11 multibyte characters' => ['ąćęłńóśźżąć'];
    }

    #[Test]
    #[DataProvider('invalidCodes')]
    public function itRejectsInvalidCodes(string $code): void
    {
        $this->expectException(InvalidCategoryCode::class);

        new Category($code);
    }

    #[Test]
    public function itAcceptsExactlyTenMultibyteCharacters(): void
    {
        self::assertSame('ąćęłńóśźżą', new Category('ąćęłńóśźżą')->getCode());
    }
}
