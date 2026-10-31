<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\NamedType;

/**
 * @phpstan-sealed StringValue|NumberValue|BooleanValue|NullValue|ObjectValue|CollectionValue
 */
abstract readonly class Value {
    protected function __construct(public NamedType|CollectionType $type) {
    }

    /**
     * Creates a value from PHP data that needs no declaration (strings, numbers, booleans, null, arrays of them, and Value).
     * Use Language::wrap() for objects.
     * @param self|string|int|float|bool|array<mixed>|null $value
     */
    public static function from(self|string|int|float|bool|array|null $value): self {
        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            $items = [];
            /** @noinspection PhpLoopCanBeConvertedToArrayMapInspection */
            foreach ($value as $key => $item) {
                $items[$key] = self::from($item);
            }
            return array_is_list($items) ? new ListValue($items) : new MapValue($items);
        }

        return match (true) {
            is_string($value) => new StringValue($value),
            is_int($value), is_float($value) => new NumberValue($value),
            is_bool($value) => new BooleanValue($value),
            $value === null => new NullValue(),
        };
    }

    abstract public function native(): mixed;
}
