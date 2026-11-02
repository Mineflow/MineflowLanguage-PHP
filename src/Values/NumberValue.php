<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromNative;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Error\DivisionByZero;
use Mineflow\Language\Syntax\BinaryOperator;
use Stringable;

#[DeclareType("number")]
readonly class NumberValue extends Value implements Stringable {
    public function __construct(public int|float $value) {
    }

    #[FromNative]
    public static function fromNative(int|float $value): self {
        return new self($value);
    }

    public function native(): int|float {
        return $this->value;
    }

    public function __toString(): string {
        return (string)$this->value;
    }

    #[Property(description: "The number rounded up.")]
    #[Method(description: "The number rounded up.")]
    public function ceil(): float {
        return ceil($this->value);
    }

    #[Property(description: "The number rounded down.")]
    #[Method(description: "The number rounded down.")]
    public function floor(): float {
        return floor($this->value);
    }

    #[Property(description: "The number rounded to the nearest whole number.")]
    #[Method(description: "The number rounded to precision decimal places.")]
    public function round(int $precision = 0): float {
        return round($this->value, $precision);
    }

    #[Property(description: "The number without its sign.")]
    #[Method(description: "The number without its sign.")]
    public function abs(): int|float {
        return abs($this->value);
    }

    #[Method(description: "The number as text, padded with zeros on the left to length characters.")]
    public function pad(int $length): string {
        return str_pad((string)$this->value, $length, "0", STR_PAD_LEFT);
    }

    #[Operator(BinaryOperator::Add, description: "The sum of both numbers.")]
    public function add(int|float $right): int|float {
        return $this->value + $right;
    }

    #[Operator(BinaryOperator::Subtract, description: "The right number taken from the left one.")]
    public function subtract(int|float $right): int|float {
        return $this->value - $right;
    }

    #[Operator(BinaryOperator::Multiply, description: "Both numbers multiplied.")]
    public function multiply(int|float $right): int|float {
        return $this->value * $right;
    }

    #[Operator(BinaryOperator::Divide, description: "The left number divided by the right one; dividing by zero is an error.")]
    public function divide(int|float $right): int|float {
        if ($right == 0) {
            throw new DivisionByZero(BinaryOperator::Divide);
        }

        return $this->value / $right;
    }
}
