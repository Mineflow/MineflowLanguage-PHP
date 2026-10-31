<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\ListValue;
use Mineflow\Language\MapValue;
use Mineflow\Language\ObjectValue;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\ListType;
use Mineflow\Language\Types\MapType;
use Mineflow\Language\Types\ObjectTypes;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\UnionType;
use Mineflow\Language\Value;

/**
 * Converts PHP values to language values.
 */
final readonly class ValueMapper {
    public function __construct(private ObjectTypes $objectTypes) {
    }

    public function wrap(mixed $value, ?Type $expected = null): Value {
        if ($value instanceof Value) {
            return $value;
        }

        if (is_array($value)) {
            $candidates = array_values(array_filter(
                $expected instanceof UnionType ? $expected->members : [$expected],
                static fn(?Type $type) => $type instanceof CollectionType,
            ));
            $collectionType = count($candidates) === 1 ? $candidates[0] : null;
            $asMap = $collectionType instanceof MapType || (!$collectionType instanceof ListType && !array_is_list($value));
            if (!$asMap && !array_is_list($value)) {
                throw new InvalidValue("Expected a PHP list, got an associative array.");
            }

            $element = $collectionType?->element;
            $items = array_map(fn($item) => $this->wrap($item, $element), $value);
            if ($asMap) {
                return new MapValue($items);
            }

            return new ListValue(array_values($items));
        }

        if (is_object($value)) {
            $type = $this->objectTypes->forObject($value) ?? throw new InvalidValue("Unregistered object: ".$value::class);
            return new ObjectValue($type, $value);
        }

        return Value::from($value);
    }
}
