<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromNative;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Syntax\BinaryOperator;
use Stringable;

#[DeclareType("boolean")]
readonly class BooleanValue extends Value implements Stringable {
    public function __construct(public bool $value) {
    }

    #[FromNative]
    public static function fromNative(bool $value): self {
        return new self($value);
    }

    public function native(): bool {
        return $this->value;
    }

    public function __toString(): string {
        return $this->value ? "true" : "false";
    }

    #[Operator(BinaryOperator::Add, description: "True when either operand is true.")]
    public function or(bool $right): bool {
        return $this->value || $right;
    }

    #[Operator(BinaryOperator::Multiply, description: "True when both operands are true.")]
    public function and(bool $right): bool {
        return $this->value && $right;
    }
}
