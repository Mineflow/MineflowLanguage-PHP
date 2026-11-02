<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\UndefinedMember;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\MemberTable;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;

/**
 * Resolves `value.name`, looking in this order:
 *
 * 1. A property of the value's type, declared on it or inherited
 * 2. The entry the #[Index] of the value's type gives for that name, declared on it or inherited
 *
 * `value.name(arguments)` calls a method of the value's type, declared on it or inherited.
 */
final readonly class MemberAccess {
    public function __construct(private MemberTable $members, private MemberInvoker $invoker) {
    }

    /**
     * @param list<Value> $arguments
     */
    public function callMethod(Value $target, string $name, array $arguments, EvaluationContext $context): Value {
        return $this->invoke(MemberReference::method($target->type()->name, $name), $target, $arguments, $context);
    }

    /**
     * @param list<Value> $arguments
     */
    public function callFunction(string $name, array $arguments, EvaluationContext $context): Value {
        return $this->invoke(MemberReference::function($name), null, $arguments, $context);
    }

    public function find(Value $value, string $name, EvaluationContext $context): ?Value {
        $typeName = $value->type()->name;
        $declared = $this->members->find(MemberReference::property($typeName, $name));
        if ($declared !== null) {
            return $this->invoker->invoke($declared, $value, [], $context);
        }

        $index = $this->members->find(MemberReference::index($typeName));
        return $index === null ? null : $this->invoker->invoke($index, $value, [new StringValue($name)], $context);
    }

    /**
     * @param list<Value> $arguments
     */
    private function invoke(MemberReference $reference, ?Value $target, array $arguments, EvaluationContext $context): Value {
        $member = $this->members->find($reference) ?? throw new UndefinedMember($reference);
        return $this->invoker->invoke($member, $target, $arguments, $context);
    }
}
