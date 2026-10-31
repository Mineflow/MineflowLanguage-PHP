<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Error\InternalError;
use Mineflow\Language\Error\ReturnTypeMismatch;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\Member;
use Mineflow\Language\Runtime\MemberTable;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\Value;

final readonly class MemberInvoker {
    public function __construct(
        private MemberTable    $members,
        private ArgumentBinder $argumentBinder = new ArgumentBinder(),
    ) {
    }

    /**
     * @param list<Value> $arguments
     */
    public function invoke(Member $member, ?Value $target, array $arguments, EvaluationContext $context): Value {
        $reference = $member->declaration->reference;
        $definition = $member->declaration->definition;
        if ($target?->type->name !== $reference->typeName) {
            throw new InternalError("$reference cannot be called on ".($target === null ? "nothing" : $target->type).".");
        }

        $arguments = $this->argumentBinder->bind($reference, $definition, $arguments, $target, fn(Value $value) => $this->entries($value, $context));
        $result = ExternalFailure::guard(static fn() => $member->implementation->call($target, $arguments, $context), $reference);

        $expected = $definition->resultType($target?->type);
        if (!$expected->accepts($result->type)) {
            throw new ReturnTypeMismatch($reference, $expected, $result->type);
        }

        return $result;
    }

    /**
     * Returns the list or map the value acts as, or null when it acts as neither.
     */
    public function entries(Value $value, EvaluationContext $context): ?CollectionValue {
        if ($value instanceof CollectionValue) {
            return $value;
        }

        $member = $this->members->find(MemberReference::entries($value->type->name));
        if ($member === null) {
            return null;
        }

        $entries = $this->invoke($member, $value, [], $context);
        return $entries instanceof CollectionValue ? $entries : throw new InternalError("The entries of $value->type are a $entries->type.");
    }
}
