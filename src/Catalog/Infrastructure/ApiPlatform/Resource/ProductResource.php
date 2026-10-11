<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Infrastructure\ApiPlatform\IdentifierRequirement;
use App\Catalog\Infrastructure\ApiPlatform\Payload\CategoryReference;
use App\Catalog\Infrastructure\ApiPlatform\Payload\MoneyPayload;
use App\Catalog\Infrastructure\ApiPlatform\State\ProductStateProcessor;
use App\Catalog\Infrastructure\ApiPlatform\State\ProductStateProvider;
use App\Catalog\Infrastructure\Validator\ExistingCategoryCodes;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Public contract of the Product aggregate.
 *
 * Categories are referenced by their unique business code rather than by IRI:
 * the code *is* the natural key of the Category aggregate, which keeps payloads
 * readable and lets clients link products without a prior lookup round-trip.
 */
#[ApiResource(
    shortName: 'Product',
    description: 'Catalog product. Always linked to at least one category.',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')", securityMessage: 'Only administrators can create products.'),
        new Put(security: "is_granted('ROLE_ADMIN')", allowCreate: false),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    requirements: ['id' => IdentifierRequirement::UUID],
    normalizationContext: ['groups' => ['product:read']],
    denormalizationContext: ['groups' => ['product:write']],
    order: ['createdAt' => 'DESC'],
    paginationClientItemsPerPage: true,
    paginationItemsPerPage: 30,
    paginationMaximumItemsPerPage: 100,
    provider: ProductStateProvider::class,
    processor: ProductStateProcessor::class,
    stateOptions: new Options(entityClass: Product::class),
)]
final class ProductResource
{
    #[ApiProperty(writable: false, identifier: true)]
    #[Groups(['product:read'])]
    public ?string $id = null;

    #[ApiProperty(example: 'Mechanical keyboard')]
    #[Groups(['product:read', 'product:write'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: Product::NAME_MAX_LENGTH)]
    public ?string $name = null;

    #[Groups(['product:read', 'product:write'])]
    #[Assert\NotNull]
    #[Assert\Valid]
    public ?MoneyPayload $price = null;

    /**
     * Write side: the list of category codes the product belongs to.
     *
     * @var list<string>
     */
    #[ApiProperty(description: 'Codes of the categories this product belongs to.', example: ['ELECTRO', 'OFFICE'])]
    #[Groups(['product:write'])]
    #[Assert\Count(min: 1, minMessage: 'A product must belong to at least one category.')]
    #[Assert\All([
        new Assert\NotBlank(normalizer: 'trim'),
        new Assert\Length(max: Category::CODE_MAX_LENGTH, normalizer: 'trim'),
    ])]
    #[ExistingCategoryCodes]
    public array $categoryCodes = [];

    /**
     * Read side: resolved categories.
     *
     * @var list<CategoryReference>
     */
    #[ApiProperty(writable: false)]
    #[Groups(['product:read'])]
    public array $categories = [];

    #[ApiProperty(writable: false)]
    #[Groups(['product:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[ApiProperty(writable: false)]
    #[Groups(['product:read'])]
    public ?\DateTimeImmutable $updatedAt = null;
}
