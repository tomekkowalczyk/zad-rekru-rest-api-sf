<?php

declare(strict_types=1);

namespace App\Security\Infrastructure\Doctrine\Repository;

use App\Security\Domain\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
