<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Repository\CategoryRepository;
use App\Catalog\Infrastructure\ApiPlatform\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\ApiPlatform\Resource\CategoryResource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<CategoryResource, CategoryResource|null>
 */
final readonly class CategoryStateProcessor implements ProcessorInterface
{
    public function __construct(
        private CategoryRepository $categories,
        private CategoryMapper $mapper,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CategoryResource
    {
        if ($operation instanceof DeleteOperationInterface) {
            $this->categories->remove($this->require($uriVariables));

            return null;
        }

        if (!$data instanceof CategoryResource) {
            throw new \LogicException(\sprintf('Expected an instance of "%s".', CategoryResource::class));
        }

        $code = (string) $data->code;

        if (isset($uriVariables['id'])) {
            $category = $this->require($uriVariables);
            $category->changeCode($code);
        } else {
            $category = new Category($code);
        }

        $this->categories->save($category);

        return $this->mapper->toResource($category);
    }

    /**
     * @param array<string, mixed> $uriVariables
     */
    private function require(array $uriVariables): Category
    {
        $id = $uriVariables['id'] ?? null;

        if (!$id instanceof Uuid) {
            $id = \is_string($id) ? Uuid::fromString($id) : throw new NotFoundHttpException('Malformed identifier.');
        }

        return $this->categories->find($id) ?? throw new NotFoundHttpException('Category not found.');
    }
}
