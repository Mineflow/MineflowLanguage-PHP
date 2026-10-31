<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Definition\Declaration;
use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Error\InternalError;
use Mineflow\Language\Error\ReturnTypeMismatch;
use Mineflow\Language\Values\Value;

final readonly class MemberInvoker {
    public function __construct(private ArgumentBinder $argumentBinder = new ArgumentBinder()) {
    }

    /**
     * @param list<Value> $arguments
     */
    public function invoke(Declaration $declaration, ?Value $target, array $arguments, EvaluationContext $context): Value {
        $reference = $declaration->reference;
        if ($target?->type->name !== $reference->typeName) {
            throw new InternalError("$reference cannot be called on ".($target === null ? "nothing" : $target->type).".");
        }

        $arguments = $this->argumentBinder->bind($reference, $declaration->definition, $arguments, $target, $context);
        $result = ExternalFailure::guard(static fn() => $declaration->implementation->call($target, $arguments, $context), $reference);

        $expected = $declaration->definition->resultType($target?->type);
        if (!$expected->accepts($result->type)) {
            throw new ReturnTypeMismatch($reference, $expected, $result->type);
        }

        return $result;
    }
}
