<?php

declare(strict_types=1);

namespace App\Catalog\Application\Notification;

use App\Catalog\Domain\Entity\Product;
use App\Notification\Domain\Notification;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Translates a saved product into a channel-agnostic notification.
 *
 * Isolated from the message handler so the wording and the payload can be unit
 * tested without touching Doctrine or Messenger.
 */
final readonly class ProductSavedNotificationFactory
{
    public const string TYPE = 'product.saved';

    /**
     * @param list<string> $recipients
     */
    public function __construct(
        #[Autowire(param: 'app.notification.catalog_recipients')]
        private array $recipients,
    ) {
    }

    public function create(Product $product, bool $wasCreated): Notification
    {
        $categories = $product->getCategoryCodes();
        $action = $wasCreated ? 'created' : 'updated';

        return new Notification(
            type: self::TYPE,
            subject: \sprintf('Product %s: %s', $action, $product->getName()),
            body: \sprintf(
                "Product \"%s\" has been %s.\n\nIdentifier: %s\nPrice: %s\nCategories: %s\nSaved at: %s",
                $product->getName(),
                $action,
                $product->getId()->toRfc4122(),
                (string) $product->getPrice(),
                implode(', ', $categories),
                $product->getUpdatedAt()->format(\DATE_ATOM),
            ),
            recipients: $this->recipients,
            context: [
                'product_id' => $product->getId()->toRfc4122(),
                'product_name' => $product->getName(),
                'price_amount' => $product->getPrice()->getAmount(),
                'price_currency' => $product->getPrice()->getCurrency(),
                'categories' => $categories,
                'was_created' => $wasCreated,
            ],
        );
    }
}
