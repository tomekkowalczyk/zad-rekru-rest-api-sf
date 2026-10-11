<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Security\Domain\User;
use App\Shared\Domain\ValueObject\Money;
use App\Tests\Support\QueryCounter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Regression test for the N+1 on the product collection: the number of queries
 * must not grow with the number of products on the page.
 */
final class ProductQueryBudgetTest extends WebTestCase
{
    #[Test]
    public function theCollectionQueryCountDoesNotDependOnThePageSize(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $token = $this->seed($client, products: 10);

        QueryCounter::reset();
        $client->request('GET', '/api/products?itemsPerPage=10', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertIsArray($payload['member'] ?? null);
        self::assertCount(10, $payload['member']);
        foreach ($payload['member'] as $product) {
            self::assertIsArray($product);
            self::assertIsArray($product['categories'] ?? null);
            self::assertCount(2, $product['categories']);
        }

        // User reload + count + paginated ids + fetch-joined page. Lazy loading
        // would add one query per product on top of that.
        self::assertLessThanOrEqual(5, QueryCounter::count(), 'Categories must be fetch-joined, not loaded per product.');
    }

    private function seed(KernelBrowser $client, int $products): string
    {
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $hasher = $container->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $entityManager->createQuery(\sprintf('DELETE FROM %s', Product::class))->execute();

        $categories = [new Category('QBA'), new Category('QBB')];
        foreach ($categories as $category) {
            $entityManager->persist($category);
        }

        for ($i = 1; $i <= $products; ++$i) {
            $entityManager->persist(new Product('Product '.$i, new Money(100 * $i, 'PLN'), $categories));
        }

        $user = new User('query-budget@example.com');
        $user->changePassword($hasher->hashPassword($user, 'secret'));
        $entityManager->persist($user);
        $entityManager->flush();
        $entityManager->clear();

        $client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'query-budget@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
        $login = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($login);
        self::assertIsString($login['token'] ?? null);

        return $login['token'];
    }
}
