<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary;

use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Builtin;

#[ExtendType(Builtin::Boolean)]
final class BooleanMembers {
    #[Operator(BinaryOperator::Add, description: "True when either operand is true.")]
    public function or(bool $left, bool $right): bool {
        return $left || $right;
    }

    #[Operator(BinaryOperator::Multiply, description: "True when both operands are true.")]
    public function and(bool $left, bool $right): bool {
        return $left && $right;
    }
}
