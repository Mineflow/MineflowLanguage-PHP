<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary;

use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Values\Value;

#[ExtendType(Builtin::String)]
final class StringMembers {
    #[Property(description: "The number of characters")]
    #[Method(description: "The number of characters")]
    public function length(string $value): int {
        return mb_strlen($value);
    }

    #[Property(description: "The string in lower case.")]
    #[Method(aliases: ["toLowerCase"], description: "The string in lower case.")]
    public function lowercase(string $value): string {
        return mb_strtolower($value);
    }

    #[Property(description: "The string in upper case.")]
    #[Method(aliases: ["toUpperCase"], description: "The string in upper case.")]
    public function uppercase(string $value): string {
        return mb_strtoupper($value);
    }

    #[Method(description: "The characters from start, up to length of them or to the end; a negative start counts from the end.")]
    public function substring(string $value, int $start, ?int $length = null): string {
        return mb_substr($value, $start, $length);
    }

    #[Operator(BinaryOperator::Add, description: "The string followed by the text of the right operand.")]
    public function concatenate(string $left, #[Param(Special::Mixed)] Value $right, #[FromContext] EvaluationContext $context): string {
        return $left.$context->text($right);
    }

    #[Operator(BinaryOperator::Subtract, description: "The string with every occurrence of the text of the right operand removed.")]
    public function remove(string $left, #[Param(Special::Mixed)] Value $right, #[FromContext] EvaluationContext $context): string {
        $text = $context->text($right);
        return $text === "" ? $left : str_replace($text, "", $left);
    }

    #[Operator(BinaryOperator::Multiply, description: "The string repeated that many times.")]
    public function repeat(string $value, int $times): string {
        return str_repeat($value, max($times, 0));
    }

    #[Operator(BinaryOperator::Multiply, reflected: true, description: "The string repeated that many times, with the number on the left.")]
    public function repeatReflected(string $value, int $times): string {
        return $this->repeat($value, $times);
    }
}
