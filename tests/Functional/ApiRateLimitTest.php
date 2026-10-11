<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Regression test: the limiter used to run before the firewall, so it never saw
 * the authenticated user and silently limited everybody per IP.
 */
final class ApiRateLimitTest extends WebTestCase
{
    #[Test]
    public function authenticatedCallersHaveTheirOwnBudget(): void
    {
        $client = self::createClient();
        $client->disableReboot();

        // Fresh identities: limiter state outlives the test transaction.
        $first = $this->createUser($client->getContainer());
        $second = $this->createUser($client->getContainer());

        $remainingForFirst = $this->remainingAfterOneRequest($client, $first);
        $remainingForSecond = $this->remainingAfterOneRequest($client, $second);

        self::assertSame(
            $remainingForFirst,
            $remainingForSecond,
            'Two users behind the same IP must not share a rate-limit bucket.',
        );
    }

    private function createUser(ContainerInterface $container): string
    {
        $email = \sprintf('rate-limit-%s@example.com', Uuid::v7()->toBase58());

        $hasher = $container->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $entityManager = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $user = new User($email);
        $user->changePassword($hasher->hashPassword($user, 'secret'));
        $entityManager->persist($user);
        $entityManager->flush();

        return $email;
    }

    private function remainingAfterOneRequest(KernelBrowser $client, string $email): string
    {
        $client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertIsString($payload['token'] ?? null);

        $client->request('GET', '/api/categories', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$payload['token'],
            'HTTP_ACCEPT' => 'application/ld+json',
        ]);
        self::assertResponseIsSuccessful();

        $remaining = $client->getResponse()->headers->get('X-RateLimit-Remaining');
        self::assertNotNull($remaining);

        return $remaining;
    }
}
