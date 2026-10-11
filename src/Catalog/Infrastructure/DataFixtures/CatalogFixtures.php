<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\DataFixtures;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Shared\Domain\ValueObject\Money;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Minimal, deterministic data set used for local development and Behat.
 */
final class CatalogFixtures extends Fixture
{
    private const array CATEGORY_CODES = ['ELECTRO', 'OFFICE', 'GAMING'];

    public function load(ObjectManager $manager): void
    {
        $categories = [];
        foreach (self::CATEGORY_CODES as $code) {
            $categories[$code] = new Category($code);
            $manager->persist($categories[$code]);
        }

        $products = [
            ['Mechanical keyboard', 34999, ['ELECTRO', 'GAMING']],
            ['Ergonomic chair', 129900, ['OFFICE']],
            ['27" monitor', 189900, ['ELECTRO', 'OFFICE']],
        ];

        foreach ($products as [$name, $amount, $codes]) {
            $manager->persist(new Product(
                $name,
                new Money($amount, 'PLN'),
                array_values(array_map(static fn (string $code): Category => $categories[$code], $codes)),
            ));
        }

        $manager->flush();
    }
}
