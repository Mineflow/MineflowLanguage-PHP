<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Error\InternalError;
use Mineflow\Language\Error\ReturnTypeMismatch;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\Member;
use Mineflow\Language\Values\Value;

final readonly class MemberInvoker {
    public function __construct(private ArgumentBinder $argumentBinder = new ArgumentBinder()) {
    }

    /**
     * @param list<Value> $arguments
     */
    public function invoke(Member $member, ?Value $target, array $arguments, EvaluationContext $context): Value {
        $reference = $member->declaration->reference;
        $definition = $member->declaration->definition;
        $targetType = $target?->type();
        if ($targetType?->name !== $reference->typeName) {
            throw new InternalError("$reference cannot be called on ".($targetType ?? "nothing").".");
        }

        $arguments = $this->argumentBinder->bind($reference, $definition, $arguments, $targetType);
        $result = ExternalFailure::guard(static fn() => $member->implementation->call($target, $arguments, $context), $reference);

        $expected = $definition->resultType($targetType);
        if (!$expected->accepts($result->type())) {
            throw new ReturnTypeMismatch($reference, $expected, $result->type());
        }

        return $result;
    }
}
