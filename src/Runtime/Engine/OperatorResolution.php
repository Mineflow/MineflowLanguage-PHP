<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use LogicException;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\Member;
use Mineflow\Language\Runtime\MemberTable;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\TypeDeclarations;
use Mineflow\Language\Values\Value;

/**
 * Computes `left symbol right`. Text that reads as a number is that number when the other operand is a number or
 * such text too. Then the first of these applies:
 *
 * 1. If the right operand is a subtype of the left operand's type and has its own reflected operator, use it.
 * 2. If the left operand's type has an operator that accepts the right operand, use it.
 * 3. If the types differ and the right operand's type has a reflected operator that accepts the left operand, use it.
 * 4. `+` with text on either side joins the text of both.
 * 5. If the left operand's type has the operator and the right operand converts to what it takes, use it.
 * 6. If the right operand's type has the reflected operator and the left operand converts to what it takes, use it.
 * 7. Calculate both operands as numbers.
 */
final readonly class OperatorResolution {
    public function __construct(private MemberTable $members, private MemberInvoker $invoker, private ValueConversion $conversion) {
    }

    public function operate(BinaryOperator $operator, Value $left, Value $right, EvaluationContext $context): Value {
        $leftNumber = NumberValue::fromNumeric($left);
        $rightNumber = NumberValue::fromNumeric($right);
        if ($leftNumber !== null && $rightNumber !== null) {
            [$left, $right] = [$leftNumber, $rightNumber];
        }

        return $this->calculate($operator, $left, $right, $context)
            ?? $this->join($operator, $left, $right)
            ?? $this->calculateConverted($operator, $left, $right, $context)
            ?? $this->calculateNumbers($operator, $left, $right, $context);
    }

    /**
     * Computes with an operator declared by either operand's type. Returns null if there is none.
     */
    private function calculate(BinaryOperator $operator, Value $left, Value $right, EvaluationContext $context): ?Value {
        $reflected = $this->accepting(MemberReference::reflectedOperator($right->type()->name, $operator), $left);
        if ($reflected !== null && $this->overrides($operator, $left, $right)) {
            return $this->invoker->invoke($reflected, $right, [$left], $context);
        }

        $declared = $this->accepting(MemberReference::operator($left->type()->name, $operator), $right);
        if ($declared !== null) {
            return $this->invoker->invoke($declared, $left, [$right], $context);
        }

        if ($reflected !== null && $left->type()->name !== $right->type()->name) {
            return $this->invoker->invoke($reflected, $right, [$left], $context);
        }

        return null;
    }

    private function join(BinaryOperator $operator, Value $left, Value $right): ?StringValue {
        return $operator === BinaryOperator::Add && ($left instanceof StringValue || $right instanceof StringValue)
            ? new StringValue($left.$right)
            : null;
    }

    private function calculateConverted(BinaryOperator $operator, Value $left, Value $right, EvaluationContext $context): ?Value {
        $declared = $this->members->find(MemberReference::operator($left->type()->name, $operator));
        $converted = $declared === null ? null : $this->convertOperand($declared, $right, $context->notices());
        if ($declared !== null && $converted !== null) {
            return $this->invoker->invoke($declared, $left, [$converted], $context);
        }

        $reflected = $this->members->find(MemberReference::reflectedOperator($right->type()->name, $operator));
        $converted = $reflected === null ? null : $this->convertOperand($reflected, $left, $context->notices());
        if ($reflected !== null && $converted !== null) {
            return $this->invoker->invoke($reflected, $right, [$converted], $context);
        }

        return null;
    }

    /**
     * An operand the operator takes as it is was already turned down by rules 1 to 3; using it here would bring
     * back a reflected operator between operands of one type, which rule 3 leaves out.
     */
    private function convertOperand(Member $operator, Value $operand, Notices $notices): ?Value {
        $expected = $operator->declaration->definition->parameters[0]->type;
        return $expected->accepts($operand->type()) ? null : $this->conversion->convert($expected, $operand, $notices);
    }

    private function calculateNumbers(BinaryOperator $operator, Value $left, Value $right, EvaluationContext $context): Value {
        $number = TypeDeclarations::of(NumberValue::class);
        $notices = $context->notices();
        $leftNumber = $this->conversion->convert($number, $left, $notices);
        $rightNumber = $this->conversion->convert($number, $right, $notices);
        assert($leftNumber instanceof NumberValue && $rightNumber instanceof NumberValue);

        return $this->calculate($operator, $leftNumber, $rightNumber, $context)
            ?? throw new LogicException("number has no {$operator->value} that takes a number");
    }

    /**
     * Whether the right operand is a subtype of the left operand's type with its own reflected operator.
     * Its own means declared on the right operand's type itself or on an ancestor that is a subtype of the left operand's type.
     * Operators inherited from the left operand's type, or from ancestors that are not its subtypes (such as a base class alongside an interface), do not count.
     */
    private function overrides(BinaryOperator $operator, Value $left, Value $right): bool {
        if ($left->type()->name === $right->type()->name || !$left->type()->accepts($right->type())) {
            return false;
        }

        $origin = $this->members->catalog->origin(MemberReference::reflectedOperator($right->type()->name, $operator))?->typeName;
        if ($origin === $right->type()->name) {
            return true;
        }

        foreach ($this->members->catalog->types->ancestors($right->type()->name) as $ancestor) {
            if ($ancestor->name === $origin) {
                return $ancestor->name !== $left->type()->name && $left->type()->accepts($ancestor);
            }
        }

        return false;
    }

    /**
     * The operator on the target value's type that accepts the other operand, or null.
     */
    private function accepting(MemberReference $operator, Value $operand): ?Member {
        $member = $this->members->find($operator);
        return $member !== null && $member->declaration->definition->parameters[0]->type->accepts($operand->type())
            ? $member
            : null;
    }
}
