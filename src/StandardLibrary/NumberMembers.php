<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary;

use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Error\DivisionByZero;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Builtin;

#[ExtendType(Builtin::Number)]
final class NumberMembers {
    #[Property(description: "The number rounded up.")]
    #[Method(description: "The number rounded up.")]
    public function ceil(int|float $value): float {
        return ceil($value);
    }

    #[Property(description: "The number rounded down.")]
    #[Method(description: "The number rounded down.")]
    public function floor(int|float $value): float {
        return floor($value);
    }

    #[Property(description: "The number rounded to the nearest whole number.")]
    #[Method(description: "The number rounded to precision decimal places.")]
    public function round(int|float $value, int $precision = 0): float {
        return round($value, $precision);
    }

    #[Property(description: "The number without its sign.")]
    #[Method(description: "The number without its sign.")]
    public function abs(int|float $value): int|float {
        return abs($value);
    }

    #[Method(description: "The number as text, padded with zeros on the left to length characters.")]
    public function pad(int|float $value, int $length): string {
        return str_pad((string)$value, $length, "0", STR_PAD_LEFT);
    }

    #[Operator(BinaryOperator::Add, description: "The sum of both numbers.")]
    public function add(int|float $left, int|float $right): int|float {
        return $left + $right;
    }

    #[Operator(BinaryOperator::Subtract, description: "The right number taken from the left one.")]
    public function subtract(int|float $left, int|float $right): int|float {
        return $left - $right;
    }

    #[Operator(BinaryOperator::Multiply, description: "Both numbers multiplied.")]
    public function multiply(int|float $left, int|float $right): int|float {
        return $left * $right;
    }

    #[Operator(BinaryOperator::Divide, description: "The left number divided by the right one; dividing by zero is an error.")]
    public function divide(int|float $left, int|float $right): int|float {
        if ($right == 0) {
            throw new DivisionByZero(BinaryOperator::Divide);
        }

        return $left / $right;
    }
}
