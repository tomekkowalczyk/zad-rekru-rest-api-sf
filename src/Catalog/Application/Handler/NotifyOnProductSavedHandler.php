<?php

declare(strict_types=1);

namespace App\Catalog\Application\Handler;

use App\Catalog\Application\Notification\ProductSavedNotificationFactory;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Event\ProductSaved;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Notification\Domain\NotificationDispatcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reacts to a committed product save by scheduling the notifications.
 *
 * Runs synchronously on the event bus (cheap, no I/O beyond one SELECT) and
 * only *enqueues* the actual deliveries, which happen in the worker.
 */
#[AsMessageHandler(bus: 'eventBus')]
final readonly class NotifyOnProductSavedHandler
{
    public function __construct(
        private ProductRepository $products,
        private ProductSavedNotificationFactory $notificationFactory,
        private NotificationDispatcher $notifications,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProductSaved $event): void
    {
        $product = $this->products->find($event->productId);

        if (!$product instanceof Product) {
            $this->logger->warning('Product disappeared before its notification was built.', [
                'product_id' => $event->productId->toRfc4122(),
            ]);

            return;
        }

        $this->notifications->dispatch($this->notificationFactory->create($product, $event->wasCreated));
    }
}
