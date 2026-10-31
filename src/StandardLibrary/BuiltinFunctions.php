<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary;

use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\MapValue;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Value;

final class BuiltinFunctions {
    #[Method(description: "The number π.")]
    public function pi(): float {
        return M_PI;
    }

    #[Method(aliases: ["rand_int"], description: "A whole number picked at random between min and max, both included.")]
    public function random(?int $min = null, ?int $max = null): int {
        return mt_rand($min ?? 0, $max ?? mt_getrandmax());
    }

    #[Method(aliases: ["rand_float"], description: "A number picked at random between 0 and 1.")]
    public function frandom(): float {
        return mt_rand() / mt_getrandmax();
    }

    #[Method(description: "The text of the value, the form a template would render it in; empty without one.")]
    public function str(#[FromContext] EvaluationContext $context, #[Param(Special::Mixed)] ?Value $value = null): string {
        return $value === null ? "" : $context->text($value);
    }

    #[Method(description: "Whether the text of the value is \"1\", \"true\" or \"yes\", in any case.")]
    public function bool(#[FromContext] EvaluationContext $context, #[Param(Special::Mixed)] ?Value $value = null): bool {
        return $value !== null && in_array(strtolower($context->text($value)), ["1", "true", "yes"], true);
    }

    #[Method(type: new MapOf(Special::Mixed, Builtin::String), description: "Every global, by name: what is read outside the run.")]
    public function vars(#[FromContext] EvaluationContext $context): MapValue {
        return new MapValue($context->scope->globals());
    }
}
