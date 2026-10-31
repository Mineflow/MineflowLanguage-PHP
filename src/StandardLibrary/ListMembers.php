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
use Mineflow\Language\Types\Expression\Nullable;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\Value;

#[ExtendType(Builtin::List)]
final class ListMembers {
    #[Property(description: "How many items the list holds.")]
    #[Method(name: "count", description: "How many items the list holds.")]
    public function length(ListValue $list): int {
        return count($list->items());
    }

    #[Method(type: new ListOf(Placeholder::Key), description: "Every index of the list, in order.")]
    public function keys(ListValue $list): ListValue {
        return $list->keys();
    }

    #[Method(type: new ListOf(Placeholder::Element), description: "Every item of the list, in order.")]
    public function values(ListValue $list): ListValue {
        return new ListValue($list->items());
    }

    #[Method(type: new Nullable(Placeholder::Element), description: "The first item, or null when the list is empty.")]
    public function first(ListValue $list): Value {
        $items = $list->items();
        return $items[0] ?? new NullValue();
    }

    #[Method(type: new Nullable(Placeholder::Element), description: "The last item, or null when the list is empty.")]
    public function last(ListValue $list): Value {
        $items = $list->items();
        return $items[count($items) - 1] ?? new NullValue();
    }

    #[Method(name: "first_key", description: "The index of the first item, or null when the list is empty.")]
    public function firstKey(ListValue $list): ?int {
        return array_key_first($list->items());
    }

    #[Method(name: "last_key", description: "The index of the last item, or null when the list is empty.")]
    public function lastKey(ListValue $list): ?int {
        return array_key_last($list->items());
    }

    #[Method(type: new Nullable(Placeholder::Element), description: "One item picked at random, or null when the list is empty.")]
    public function random(ListValue $list): Value {
        $items = $list->items();
        return $items === [] ? new NullValue() : $items[array_rand($items)];
    }

    #[Method(type: new Nullable(new ListOf(Special::Mixed)), description: "The property of that name read off every item, or null when one item has none.")]
    public function pluck(ListValue $list, string $name, #[FromContext] EvaluationContext $context): Value {
        return $context->pluck($list, $name) ?? new NullValue();
    }

    #[Method(type: Placeholder::Target, aliases: ["reversed"], description: "The items in reverse order.")]
    public function reverse(ListValue $list): ListValue {
        return new ListValue(array_reverse($list->items()));
    }

    #[Method(type: Placeholder::Target, description: "The first amount items.")]
    public function take(ListValue $list, int $amount): ListValue {
        return new ListValue(array_slice($list->items(), 0, $amount));
    }

    #[Method(name: "take_last", type: Placeholder::Target, aliases: ["takeLast"], description: "The last amount items.")]
    public function takeLast(ListValue $list, int $amount): ListValue {
        $items = $list->items();
        return new ListValue(array_slice($items, max(count($items) - $amount, 0)));
    }

    #[Method(type: Placeholder::Target, aliases: ["shuffled"], description: "The items in random order.")]
    public function shuffle(ListValue $list): ListValue {
        $items = $list->items();
        shuffle($items);
        return new ListValue($items);
    }

    #[Method(description: "Joins the textual form of every item, the form a template would render it in.")]
    public function join(ListValue $list, #[FromContext] EvaluationContext $context, string $separator = ","): string {
        return implode($separator, array_map($context->text(...), $list->items()));
    }

    #[Operator(BinaryOperator::Add, description: "Every item with the right operand added to it.")]
    public function add(ListValue $list, #[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): ListValue {
        return $this->each(BinaryOperator::Add, $list, $operand, $context);
    }

    #[Operator(BinaryOperator::Subtract, description: "Every item with the right operand subtracted from it.")]
    public function subtract(ListValue $list, #[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): ListValue {
        return $this->each(BinaryOperator::Subtract, $list, $operand, $context);
    }

    #[Operator(BinaryOperator::Multiply, description: "Every item multiplied by the right operand.")]
    public function multiply(ListValue $list, #[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): ListValue {
        return $this->each(BinaryOperator::Multiply, $list, $operand, $context);
    }

    #[Operator(BinaryOperator::Divide, description: "Every item divided by the right operand.")]
    public function divide(ListValue $list, #[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): ListValue {
        return $this->each(BinaryOperator::Divide, $list, $operand, $context);
    }

    private function each(BinaryOperator $operator, ListValue $list, Value $operand, EvaluationContext $context): ListValue {
        if ($operand instanceof CollectionValue) {
            throw new UnsupportedCalculation($operator, $list->type, $operand->type);
        }

        return new ListValue(array_map(static fn(Value $item) => $context->operate($operator, $item, $operand), $list->items()));
    }
}
