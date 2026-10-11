<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Validator;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Repository\CategoryRepository;
use App\Catalog\Infrastructure\ApiPlatform\Resource\CategoryResource;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UniqueCategoryCodeValidator extends ConstraintValidator
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueCategoryCode) {
            throw new UnexpectedValueException($constraint, UniqueCategoryCode::class);
        }

        if (!$value instanceof CategoryResource) {
            throw new UnexpectedValueException($value, CategoryResource::class);
        }

        if (null === $value->code || '' === trim($value->code)) {
            return;
        }

        // Same normalisation as the entity, otherwise " ELECTRO" slips past this
        // check and hits the unique index as "ELECTRO".
        $code = trim($value->code);
        $existing = $this->categories->findOneByCode($code);

        if (!$existing instanceof Category || $existing->getId()->toRfc4122() === $value->id) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ code }}', $code)
            ->atPath('code')
            ->addViolation();
    }
}
