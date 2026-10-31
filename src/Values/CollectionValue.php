<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

/**
 * @phpstan-sealed ListValue|MapValue
 */
abstract readonly class CollectionValue extends Value {
    /**
     * @return array<string|int, Value>
     */
    abstract public function items(): array;

    public function item(string|int $key): ?Value {
        return $this->items()[$key] ?? null;
    }

    abstract public function without(string|int $key): static;

    public function keys(): ListValue {
        return new ListValue(array_map(
            static fn(string|int $key) => is_int($key) ? new NumberValue($key) : new StringValue($key),
            array_keys($this->items()),
        ));
    }

    /**
     * @return array<string|int, mixed>
     */
    public function native(): array {
        return array_map(static fn(Value $v) => $v->native(), $this->items());
    }
}
