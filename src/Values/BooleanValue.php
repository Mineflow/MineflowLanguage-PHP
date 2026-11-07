<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromNative;
use Mineflow\Language\Attribute\FromValue;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Syntax\BinaryOperator;

#[DeclareType("boolean")]
readonly class BooleanValue extends Value {
    public function __construct(public bool $value) {
    }

    #[FromNative]
    public static function fromNative(bool $value): self {
        return new self($value);
    }

    #[FromValue]
    public static function fromValue(Value $value, Notices $notices): self {
        return new self(match (true) {
            $value instanceof StringValue => in_array(strtolower($value->value), ["1", "true", "yes"], true),
            $value instanceof NumberValue => $value->value != 0,
            $value instanceof CollectionValue => $value->items() !== [],
            $value instanceof NullValue => false,
            default => true,
        });
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
