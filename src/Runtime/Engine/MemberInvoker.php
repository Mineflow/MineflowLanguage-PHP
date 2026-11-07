<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Error\ReturnTypeMismatch;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\Member;
use Mineflow\Language\Values\Value;

final readonly class MemberInvoker {
    public function __construct(private ArgumentBinder $argumentBinder) {
    }

    /**
     * @param list<Value> $arguments
     */
    public function invoke(Member $member, ?Value $target, array $arguments, EvaluationContext $context): Value {
        $reference = $member->declaration->reference;
        $definition = $member->declaration->definition;

        $arguments = $this->argumentBinder->bind($reference, $definition, $arguments, $context->notices());
        $result = $member->implementation->call($target, $arguments, $context);

        if (!$definition->returns->accepts($result->type())) {
            throw new ReturnTypeMismatch($reference, $definition->returns, $result->type());
        }

        return $result;
    }
}
