<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Error\ArgumentTypeMismatch;
use Mineflow\Language\Error\MissingArgument;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\UnionType;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\Value;

final readonly class ArgumentBinder {
    public function __construct(private ValueConversion $conversion) {
    }

    /**
     * @param list<Value> $arguments
     * @return list<Value>
     */
    public function bind(MemberReference $reference, MemberDefinition $definition, array $arguments, Notices $notices): array {
        $variadic = false;

        foreach ($definition->parameters as $i => $parameter) {
            $expected = $parameter->type;
            if ($parameter->variadic) {
                $variadic = true;

                for ($j = $i; $j < count($arguments); $j++) {
                    $arguments[$j] = $this->coerceArgument($expected, $arguments[$j], $parameter->name, $reference, $notices);
                }
                break;
            }

            if (!isset($arguments[$i])) {
                if (!$parameter->optional) {
                    throw new MissingArgument($reference, $parameter->name);
                }
            } else {
                $arguments[$i] = $this->coerceArgument($expected, $arguments[$i], $parameter->name, $reference, $notices);
            }
        }

        // Arguments beyond the parameters are dropped, not refused.
        return $variadic ? $arguments : array_slice($arguments, 0, count($definition->parameters));
    }

    /**
     * @param bool $converts Whether the value may be replaced by its conversion: false for an item of a collection that
     *     cannot be rebuilt with other items.
     */
    private function coerceArgument(Type $expected, Value $value, string $name, MemberReference $reference, Notices $notices, bool $converts = true): Value {
        if (!$expected->accepts($value->type())) {
            $trial = new Notices();
            $converted = $converts ? $this->conversion->convert($expected, $value, $trial) : null;
            if ($converted === null) {
                throw new ArgumentTypeMismatch($reference, $name, $expected, $value->type());
            }

            foreach ($trial->all() as $notice) {
                $notices->add("$name of $reference: $notice->message");
            }
            $value = $converted;
        }

        return $value instanceof CollectionValue ? $this->coerceItems($expected, $value, $name, $reference, $notices, $converts) : $value;
    }

    /**
     * Checks every item against the element type the parameter gives, each as an argument of its own, since a
     * collection is accepted whatever it holds. A plain list or map gets its items converted; one of another class is
     * passed as it is, so its items have to be what the parameter asks for.
     */
    private function coerceItems(Type $expected, CollectionValue $collection, string $name, MemberReference $reference, Notices $notices, bool $converts): CollectionValue {
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
            $items[$key] = $this->coerceArgument($element, $item, "{$name}[$key]", $reference, $notices, $converts);
            $converted = $converted || $items[$key] !== $item;
        }

        if (!$converted) {
            return $collection;
        }
        return $collection instanceof ListValue ? new ListValue(array_values($items), $element) : new MapValue($items, $element);
    }
}
