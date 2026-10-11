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
use App\Catalog\Infrastructure\ApiPlatform\IdentifierRequirement;
use App\Catalog\Infrastructure\ApiPlatform\State\CategoryStateProcessor;
use App\Catalog\Infrastructure\ApiPlatform\State\CategoryStateProvider;
use App\Catalog\Infrastructure\Validator\UniqueCategoryCode;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Public contract of the Category aggregate.
 *
 * Keeping the resource separate from the entity means the HTTP payload can
 * evolve independently from the database schema, and the aggregate keeps its
 * invariants instead of exposing public setters to the serialiser.
 */
#[ApiResource(
    shortName: 'Category',
    description: 'Product category identified by a unique, at most 10 characters long code.',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(security: "is_granted('ROLE_ADMIN')", securityMessage: 'Only administrators can create categories.'),
        new Put(security: "is_granted('ROLE_ADMIN')", allowCreate: false),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    requirements: ['id' => IdentifierRequirement::UUID],
    normalizationContext: ['groups' => ['category:read']],
    denormalizationContext: ['groups' => ['category:write']],
    order: ['code' => 'ASC'],
    paginationClientItemsPerPage: true,
    paginationItemsPerPage: 30,
    paginationMaximumItemsPerPage: 100,
    provider: CategoryStateProvider::class,
    processor: CategoryStateProcessor::class,
    stateOptions: new Options(entityClass: Category::class),
)]
#[UniqueCategoryCode]
final class CategoryResource
{
    #[ApiProperty(writable: false, identifier: true)]
    #[Groups(['category:read'])]
    public ?string $id = null;

    #[ApiProperty(description: 'Unique business key.', example: 'ELECTRO')]
    #[Groups(['category:read', 'category:write'])]
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: Category::CODE_MAX_LENGTH, normalizer: 'trim')]
    public ?string $code = null;

    #[ApiProperty(writable: false)]
    #[Groups(['category:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[ApiProperty(writable: false)]
    #[Groups(['category:read'])]
    public ?\DateTimeImmutable $updatedAt = null;
}
