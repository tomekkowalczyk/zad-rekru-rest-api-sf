<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Repository\CategoryRepository;
use App\Catalog\Domain\Repository\ProductRepository;
use App\Catalog\Infrastructure\ApiPlatform\Mapper\ProductMapper;
use App\Catalog\Infrastructure\ApiPlatform\Payload\MoneyPayload;
use App\Catalog\Infrastructure\ApiPlatform\Resource\ProductResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Turns the validated write model into aggregate method calls.
 *
 * The aggregate is never mutated by the serialiser: the processor is the single
 * place where HTTP semantics are translated into domain behaviour.
 *
 * @implements ProcessorInterface<ProductResource, ProductResource|null>
 */
final readonly class ProductStateProcessor implements ProcessorInterface
{
    public function __construct(
        private ProductRepository $products,
        private CategoryRepository $categories,
        private ProductMapper $mapper,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProductResource
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->products->remove($this->require($uriVariables));

            return null;
        }

        if (!$data instanceof ProductResource) {
            throw new \LogicException(\sprintf('Expected an instance of "%s".', ProductResource::class));
        }

        $product = isset($uriVariables['id'])
            ? $this->update($this->require($uriVariables), $data)
            : $this->create($data);

        $this->products->save($product);

        return $this->mapper->toResource($product);
    }

    private function create(ProductResource $data): Product
    {
        return new Product(
            name: (string) $data->name,
            price: ($data->price ?? throw new \LogicException('Price must be validated before processing.'))->toMoney(),
            categories: $this->resolveCategories($data),
        );
    }

    private function update(Product $product, ProductResource $data): Product
    {
        $product->rename((string) $data->name);

        if ($data->price instanceof MoneyPayload) {
            $product->changePrice($data->price->toMoney());
        }

        $product->replaceCategories($this->resolveCategories($data));

        return $product;
    }

    /**
     * @return non-empty-list<Category>
     */
    private function resolveCategories(ProductResource $data): array
    {
        $categories = $this->categories->findByCodes(array_map(trim(...), $data->categoryCodes));

        if ([] === $categories) {
            // Unreachable through HTTP: ExistingCategoryCodes and Count(min=1)
            // already rejected the payload with a 422.
            throw new \LogicException('No category resolved for the product.');
        }

        return $categories;
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function require(array $uriVariables): Product
    {
        $id = $uriVariables['id'] ?? null;

        if (!$id instanceof Uuid) {
            $id = \is_string($id) ? Uuid::fromString($id) : throw new NotFoundHttpException('Malformed identifier.');
        }

        return $this->products->find($id) ?? throw new NotFoundHttpException('Product not found.');
    }
}
