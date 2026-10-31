<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Definition\Catalog;
use Mineflow\Language\Definition\Declaration;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Value;

/**
 * Computes `left symbol right` with the first operator that applies:
 *
 * 1. If the right operand is a subtype of the left operand's type and has its own reflected operator, use it.
 * 2. If the left operand's type has an operator that accepts the right operand, use it.
 * 3. If the types differ and the right operand's type has a reflected operator that accepts the left operand, use it.
 * 4. Otherwise, compute object types as their collections of entries (`config + 1`).
 *
 * If it cannot be computed, the error names the types of both operands as written.
 */
final readonly class OperatorResolution {
    public function __construct(private Catalog $catalog, private MemberInvoker $invoker) {
    }

    public function operate(BinaryOperator $operator, Value $left, Value $right, EvaluationContext $context): Value {
        try {
            return $this->calculate($operator, $left, $right, $context)
                ?? $this->calculate($operator, $this->actual($left, $context), $this->actual($right, $context), $context)
                ?? throw new UnsupportedCalculation($operator, $left->type, $right->type);
        } catch (UnsupportedCalculation $error) {
            $refused = $error->operator === $operator
                && $error->left->equals($this->actual($left, $context)->type)
                && $error->right->equals($this->actual($right, $context)->type);
            throw $refused ? new UnsupportedCalculation($operator, $left->type, $right->type) : $error;
        }
    }

    /**
     * Computes with an operator declared by either operand's type. Returns null if there is none.
     */
    private function calculate(BinaryOperator $operator, Value $left, Value $right, EvaluationContext $context): ?Value {
        $reflected = $this->accepting(MemberReference::reflectedOperator($right->type->name, $operator), $right, $left);
        if ($reflected !== null && $this->overrides($operator, $left, $right)) {
            return $this->invoker->invoke($reflected, $right, [$left], $context);
        }

        $declared = $this->accepting(MemberReference::operator($left->type->name, $operator), $left, $right);
        if ($declared !== null) {
            return $this->invoker->invoke($declared, $left, [$right], $context);
        }

        if ($reflected !== null && $left->type->name !== $right->type->name) {
            return $this->invoker->invoke($reflected, $right, [$left], $context);
        }

        return null;
    }

    /**
     * Whether the right operand is a subtype of the left operand's type with its own reflected operator.
     * Its own means declared on the right operand's type itself or on an ancestor that is a subtype of the left operand's type.
     * Operators inherited from the left operand's type, or from ancestors that are not its subtypes (such as a base class alongside an interface), do not count.
     */
    private function overrides(BinaryOperator $operator, Value $left, Value $right): bool {
        if ($left->type->name === $right->type->name || !$left->type->accepts($right->type)) {
            return false;
        }

        $origin = $this->catalog->origin(MemberReference::reflectedOperator($right->type->name, $operator))?->typeName;
        if ($origin === $right->type->name) {
            return true;
        }

        foreach ($this->catalog->objectTypes->ancestors($right->type->name) as $ancestor) {
            if ($ancestor->name === $origin) {
                return $ancestor->name !== $left->type->name && $left->type->accepts($ancestor);
            }
        }

        return false;
    }

    /**
     * The operator on the target value's type that accepts the other operand, or null.
     */
    private function accepting(MemberReference $operator, Value $target, Value $operand): ?Declaration {
        $declaration = $this->catalog->declaration($operator);
        return $declaration !== null && $declaration->definition->parameters[0]->type->resolvePlaceholders($target->type)->accepts($operand->type)
            ? $declaration
            : null;
    }

    private function actual(Value $value, EvaluationContext $context): Value {
        return $context->asCollection($value) ?? $value;
    }
}
