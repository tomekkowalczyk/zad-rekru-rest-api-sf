<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\DataFixtures;

use App\Security\Domain\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Development accounts. Credentials are intentionally trivial and the fixture
 * is never loaded outside the dev/test environments.
 */
final class SecurityFixtures extends Fixture
{
    private const array ACCOUNTS = [
        ['admin@example.com', 'admin', ['ROLE_ADMIN']],
        ['reader@example.com', 'reader', []],
    ];

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::ACCOUNTS as [$email, $plainPassword, $roles]) {
            $user = new User($email, $roles);
            $user->changePassword($this->passwordHasher->hashPassword($user, $plainPassword));
            $manager->persist($user);
        }

        $manager->flush();
    }
}
