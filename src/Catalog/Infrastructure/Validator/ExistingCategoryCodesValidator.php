<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Validator;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Repository\CategoryRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ExistingCategoryCodesValidator extends ConstraintValidator
{
    public function __construct(private readonly CategoryRepository $categories)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ExistingCategoryCodes) {
            throw new UnexpectedValueException($constraint, ExistingCategoryCodes::class);
        }

        if (null === $value || [] === $value) {
            return;
        }

        if (!\is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        $requested = array_values(array_unique(array_filter(
            array_map(static fn (mixed $code): string => \is_string($code) ? trim($code) : '', $value),
            static fn (string $code): bool => '' !== $code,
        )));

        if ([] === $requested) {
            return;
        }

        // The lookup follows the column collation (case-insensitive), so the
        // comparison must too, or "electro" is found and reported missing at once.
        $found = array_map(
            static fn (Category $category): string => mb_strtolower($category->getCode()),
            $this->categories->findByCodes($requested),
        );

        $missing = array_values(array_filter(
            $requested,
            static fn (string $code): bool => !\in_array(mb_strtolower($code), $found, true),
        ));

        if ([] === $missing) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ codes }}', implode(', ', $missing))
            ->addViolation();
    }
}
