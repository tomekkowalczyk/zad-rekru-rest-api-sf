<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Notification\Application\Command\SendNotification;
use App\Security\Domain\User;
use App\Shared\Domain\ValueObject\Money;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * Owns the database state and the notification queue assertions.
 */
final readonly class CatalogContext implements Context
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private InMemoryTransport $notificationQueue,
    ) {
    }

    #[Given('the catalog is empty')]
    public function theCatalogIsEmpty(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        foreach (['product_category', 'product', 'category', 'operation_log', 'app_user'] as $table) {
            $connection->executeStatement(\sprintf('DELETE FROM %s', $table));
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->entityManager->clear();
        $this->notificationQueue->reset();
    }

    #[Given('the following categories exist:')]
    public function theFollowingCategoriesExist(TableNode $table): void
    {
        foreach ($table->getHash() as $row) {
            $this->entityManager->persist(new Category($row['code'], self::optionalId($row)));
        }

        $this->entityManager->flush();
    }

    #[Given('the following products exist:')]
    public function theFollowingProductsExist(TableNode $table): void
    {
        foreach ($table->getHash() as $row) {
            $codes = array_map(trim(...), explode(',', $row['categories']));
            $categories = $this->entityManager->getRepository(Category::class)->findBy(['code' => $codes]);

            Assert::notEmpty($categories, \sprintf('Unknown categories: %s', $row['categories']));

            $this->entityManager->persist(new Product(
                $row['name'],
                new Money((int) $row['amount'], $row['currency'] ?? 'PLN'),
                array_values($categories),
                self::optionalId($row),
            ));
        }

        $this->entityManager->flush();
        $this->notificationQueue->reset();
    }

    #[Given('a user :email with password :password and role :role')]
    public function aUserWithPasswordAndRole(string $email, string $password, string $role): void
    {
        $user = new User($email, 'ROLE_USER' === $role ? [] : [$role]);
        $user->changePassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    #[Given('the notification queue is empty')]
    public function theNotificationQueueIsEmpty(): void
    {
        $this->notificationQueue->reset();
    }

    #[Then('the notification queue should contain :count message(s)')]
    public function theNotificationQueueShouldContainMessages(int $count): void
    {
        Assert::count($this->notificationQueue->getSent(), $count);
    }

    #[Then('a notification should be queued for the :channel channel with subject :subject')]
    public function aNotificationShouldBeQueuedForChannel(string $channel, string $subject): void
    {
        foreach ($this->notificationQueue->getSent() as $envelope) {
            $message = $envelope->getMessage();

            if ($message instanceof SendNotification
                && $message->channel === $channel
                && $message->notification->subject === $subject
            ) {
                return;
            }
        }

        Assert::true(false, \sprintf('No "%s" notification queued with subject "%s".', $channel, $subject));
    }

    /**
     * Lets a scenario address a resource by a well-known identifier.
     *
     * @param array<string, string> $row
     */
    private static function optionalId(array $row): ?Uuid
    {
        return isset($row['id']) && '' !== $row['id'] ? Uuid::fromString($row['id']) : null;
    }
}
