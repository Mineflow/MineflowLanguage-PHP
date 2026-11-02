<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Definition\MemberKind;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\ArgumentTypeMismatch;
use Mineflow\Language\Error\MissingArgument;
use Mineflow\Language\Error\TooManyArguments;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\UnionType;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\TypeDeclarations;
use Mineflow\Language\Values\Value;

final readonly class ArgumentBinder {
    /**
     * @param list<Value> $arguments
     * @return list<Value>
     */
    public function bind(MemberReference $reference, MemberDefinition $definition, array $arguments): array {
        $variadic = false;

        foreach ($definition->parameters as $i => $parameter) {
            $expected = $parameter->type;
            if ($parameter->variadic) {
                $variadic = true;

                for ($j = $i; $j < count($arguments); $j++) {
                    $arguments[$j] = $this->coerceArgument($expected, $arguments[$j], $parameter->name, $reference);
                }
                break;
            }

            if (!isset($arguments[$i])) {
                if (!$parameter->optional) {
                    throw new MissingArgument($reference, $parameter->name);
                }
            } else {
                $arguments[$i] = $this->coerceArgument($expected, $arguments[$i], $parameter->name, $reference);
            }
        }

        if (!$variadic && count($arguments) > count($definition->parameters)) {
            throw new TooManyArguments($reference, count($arguments));
        }

        return $arguments;
    }

    /**
     * @param bool $converts Whether the value may be replaced by its conversion: false for an item of a collection that
     *     cannot be rebuilt with other items.
     */
    private function coerceArgument(Type $expected, Value $value, string $name, MemberReference $reference, bool $converts = true): Value {
        if (!$expected->accepts($value->type())) {
            if ($converts && in_array($reference->kind, [MemberKind::Method, MemberKind::Function], true)
                && $value instanceof StringValue && is_numeric($value->value)
                && $expected->accepts(TypeDeclarations::of(NumberValue::class))) {
                return new NumberValue($value->value + 0);
            }

            throw new ArgumentTypeMismatch($reference, $name, $expected, $value->type());
        }

        return $value instanceof CollectionValue ? $this->coerceItems($expected, $value, $name, $reference, $converts) : $value;
    }

    /**
     * Checks every item against the element type the parameter gives, each as an argument of its own, since a
     * collection is accepted whatever it holds. A plain list or map gets its items converted; one of another class is
     * passed as it is, so its items have to be what the parameter asks for.
     */
    private function coerceItems(Type $expected, CollectionValue $collection, string $name, MemberReference $reference, bool $converts): CollectionValue {
        $candidates = array_filter(
            $expected instanceof UnionType ? $expected->members : [$expected],
            static fn(Type $type) => $type instanceof CollectionType && $type->accepts($collection->type()),
        );
        $element = Type::union(...array_map(static fn(CollectionType $type) => $type->element, $candidates));
        if ($candidates === [] || $element->accepts(Type::mixed())) {
            return $collection;
        }

        $converts = $converts && in_array($collection::class, [ListValue::class, MapValue::class], true);
        $items = [];
        $converted = false;
        foreach ($collection->items() as $key => $item) {
            $items[$key] = $this->coerceArgument($element, $item, "{$name}[$key]", $reference, $converts);
            $converted = $converted || $items[$key] !== $item;
        }

        if (!$converted) {
            return $collection;
        }
        return $collection instanceof ListValue ? new ListValue(array_values($items), $element) : new MapValue($items, $element);
    }
}
