<?php
declare(strict_types=1);

namespace Mineflow\Language\Format;

use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Error\NoTextRepresentation;
use Mineflow\Language\Values\BooleanValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\ObjectValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use Stringable;

final readonly class DefaultValueFormatter implements ValueFormatter {
    public function format(Value $value): string {
        return match (true) {
            $value instanceof StringValue, $value instanceof NumberValue => (string)$value->value,
            $value instanceof BooleanValue => $value->value ? "true" : "false",
            $value instanceof NullValue => "null",
            $value instanceof ListValue => "[".implode(",", array_map($this->format(...), $value->items()))."]",
            $value instanceof MapValue => "<".implode(",", array_map(
                    fn($key, Value $item) => $key.":".$this->format($item), array_keys($value->items()), array_values($value->items())
                )).">",
            $value instanceof ObjectValue && $value->value instanceof Stringable => ExternalFailure::guard(static fn() => (string)$value->value),
            default => throw new NoTextRepresentation($value->type),
        };
    }
}
