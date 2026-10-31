<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary;

use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Expression\Nullable;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\Value;

#[ExtendType(Builtin::Map)]
final class MapMembers {
    #[Property(description: "How many entries the map holds.")]
    #[Method(name: "count", description: "How many entries the map holds.")]
    public function length(MapValue $map): int {
        return count($map->items());
    }

    #[Method(type: new ListOf(Placeholder::Key), description: "Every key of the map.")]
    public function keys(MapValue $map): ListValue {
        return new ListValue(array_map(Value::from(...), array_keys($map->items())));
    }

    #[Method(type: new ListOf(Placeholder::Element), description: "Every value of the map.")]
    public function values(MapValue $map): ListValue {
        return new ListValue(array_values($map->items()));
    }

    #[Method(type: new Nullable(Placeholder::Element), description: "The first value, or null when the map is empty.")]
    public function first(MapValue $map): Value {
        $items = $map->items();
        return $items === [] ? new NullValue() : $items[array_key_first($items)];
    }

    #[Method(type: new Nullable(Placeholder::Element), description: "The last value, or null when the map is empty.")]
    public function last(MapValue $map): Value {
        $items = $map->items();
        return $items === [] ? new NullValue() : $items[array_key_last($items)];
    }

    #[Method(name: "first_key", type: new Nullable(Placeholder::Key), description: "The first key, or null when the map is empty.")]
    public function firstKey(MapValue $map): string|int|null {
        return array_key_first($map->items());
    }

    #[Method(name: "last_key", type: new Nullable(Placeholder::Key), description: "The last key, or null when the map is empty.")]
    public function lastKey(MapValue $map): string|int|null {
        return array_key_last($map->items());
    }

    #[Method(type: new Nullable(Placeholder::Element), description: "One value picked at random, or null when the map is empty.")]
    public function random(MapValue $map): Value {
        $items = $map->items();
        return $items === [] ? new NullValue() : $items[array_rand($items)];
    }

    #[Method(type: new Nullable(new MapOf(Special::Mixed, Placeholder::Key)), description: "The property of that name read off every value, or null when one value has none.")]
    public function pluck(MapValue $map, string $name, #[FromContext] EvaluationContext $context): Value {
        return $context->pluck($map, $name) ?? new NullValue();
    }

    #[Method(type: Placeholder::Target, aliases: ["reversed"], description: "The entries in reverse order.")]
    public function reverse(MapValue $map): MapValue {
        return new MapValue(array_reverse($map->items(), true));
    }

    #[Method(type: Placeholder::Target, description: "The first amount entries.")]
    public function take(MapValue $map, int $amount): MapValue {
        return new MapValue(array_slice($map->items(), 0, $amount, true));
    }

    #[Method(name: "take_last", type: Placeholder::Target, aliases: ["takeLast"], description: "The last amount entries.")]
    public function takeLast(MapValue $map, int $amount): MapValue {
        $items = $map->items();
        return new MapValue(array_slice($items, max(count($items) - $amount, 0), null, true));
    }

    #[Method(type: new MapOf(Placeholder::Element, Builtin::Number), aliases: ["shuffled"], description: "The values in random order, keyed by their new position.")]
    public function shuffle(MapValue $map): MapValue {
        $items = array_values($map->items());
        shuffle($items);
        return new MapValue($items);
    }

    #[Operator(BinaryOperator::Add, type: new MapOf(Special::Mixed, Placeholder::Key), description: "Every value with the right operand added to it.")]
    public function add(MapValue $map, #[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): MapValue {
        return $this->each(BinaryOperator::Add, $map, $operand, $context);
    }

    #[Operator(BinaryOperator::Subtract, type: new MapOf(Special::Mixed, Placeholder::Key), description: "Every value with the right operand subtracted from it.")]
    public function subtract(MapValue $map, #[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): MapValue {
        return $this->each(BinaryOperator::Subtract, $map, $operand, $context);
    }

    #[Operator(BinaryOperator::Multiply, type: new MapOf(Special::Mixed, Placeholder::Key), description: "Every value multiplied by the right operand.")]
    public function multiply(MapValue $map, #[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): MapValue {
        return $this->each(BinaryOperator::Multiply, $map, $operand, $context);
    }

    #[Operator(BinaryOperator::Divide, type: new MapOf(Special::Mixed, Placeholder::Key), description: "Every value divided by the right operand.")]
    public function divide(MapValue $map, #[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): MapValue {
        return $this->each(BinaryOperator::Divide, $map, $operand, $context);
    }

    private function each(BinaryOperator $operator, MapValue $map, Value $operand, EvaluationContext $context): MapValue {
        if ($operand instanceof CollectionValue) {
            throw new UnsupportedCalculation($operator, $map->type, $operand->type);
        }

        return new MapValue(array_map(static fn(Value $item) => $context->operate($operator, $item, $operand), $map->items()));
    }
}
