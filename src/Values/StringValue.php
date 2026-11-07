<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromNative;
use Mineflow\Language\Attribute\FromValue;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Syntax\BinaryOperator;

#[DeclareType("string")]
readonly class StringValue extends Value {
    public function __construct(public string $value) {
    }

    #[FromNative]
    public static function fromNative(string $value): self {
        return new self($value);
    }

    #[FromValue]
    public static function fromValue(Value $value, Notices $notices): self {
        return new self((string)$value);
    }

    public function native(): string {
        return $this->value;
    }

    public function __toString(): string {
        return $this->value;
    }

    #[Property(description: "The number of characters")]
    #[Method(description: "The number of characters")]
    public function length(): int {
        return mb_strlen($this->value);
    }

    #[Property(description: "The string in lower case.")]
    #[Method(aliases: ["toLowerCase"], description: "The string in lower case.")]
    public function lowercase(): string {
        return mb_strtolower($this->value);
    }

    #[Property(description: "The string in upper case.")]
    #[Method(aliases: ["toUpperCase"], description: "The string in upper case.")]
    public function uppercase(): string {
        return mb_strtoupper($this->value);
    }

    #[Method(description: "The characters from start, up to length of them or to the end; a negative start counts from the end.")]
    public function substring(int $start, ?int $length = null): string {
        return mb_substr($this->value, $start, $length);
    }

    #[Operator(BinaryOperator::Add, description: "The string followed by the text of the right operand.")]
    public function concatenate(Value $right): string {
        return $this->value.$right;
    }

    #[Operator(BinaryOperator::Subtract, description: "The string with every occurrence of the text of the right operand removed.")]
    public function remove(Value $right): string {
        $text = (string)$right;
        return $text === "" ? $this->value : str_replace($text, "", $this->value);
    }

    #[Operator(BinaryOperator::Multiply, description: "The string repeated that many times.")]
    public function repeat(int $times): string {
        return str_repeat($this->value, max($times, 0));
    }

    #[Operator(BinaryOperator::Multiply, reflected: true, description: "The string repeated that many times, with the number on the left.")]
    public function repeatReflected(int $times): string {
        return $this->repeat($times);
    }
}
