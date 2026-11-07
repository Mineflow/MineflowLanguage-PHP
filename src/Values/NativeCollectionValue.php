<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

/**
 * A collection whose items the host keeps as PHP values, such as the data of a config file, and makes into values
 * only when they are read: `c.money` makes the one item under `money`, `c.length` and `c.keys()` make none, and
 * `c.name`, read off every item, stops at the first item without it. Only a member that takes every item, such as
 * values() or a parameter of a list, makes them all.
 *
 * The items are read from nativeItems() each time a source reaches them, so a change to the host's data is seen
 * by the next read.
 */
abstract readonly class NativeCollectionValue extends CollectionValue {
    /**
     * The items as PHP values, under their keys. Called on every read, so it should give the data the host holds
     * rather than build it.
     * @return array<string|int, mixed>
     */
    abstract protected function nativeItems(): array;

    /**
     * Makes one item into a value. By default a PHP value is converted the way a scope converts it, with the
     * conversions of the standard types.
     */
    protected function convert(mixed $item): Value {
        return (new ValueMapper())->wrap($item);
    }

    final public function items(): array {
        return array_map($this->convert(...), $this->nativeItems());
    }

    public function item(string|int $key): ?Value {
        $items = $this->nativeItems();
        return array_key_exists($key, $items) ? $this->convert($items[$key]) : null;
    }

    public function length(): int {
        return count($this->nativeItems());
    }

    public function keys(): ListValue {
        return new ListValue(array_map(
            static fn(string|int $key) => is_int($key) ? new NumberValue($key) : new StringValue($key),
            array_keys($this->nativeItems()),
        ));
    }

    public function first(): Value {
        return $this->itemOrNull(array_key_first($this->nativeItems()));
    }

    public function last(): Value {
        return $this->itemOrNull(array_key_last($this->nativeItems()));
    }

    public function firstKey(): string|int|null {
        return array_key_first($this->nativeItems());
    }

    public function lastKey(): string|int|null {
        return array_key_last($this->nativeItems());
    }

    public function random(): Value {
        $items = $this->nativeItems();
        return $this->itemOrNull($items === [] ? null : array_rand($items));
    }

    protected function eachItem(): iterable {
        foreach ($this->nativeItems() as $key => $item) {
            yield $key => $this->convert($item);
        }
    }

    private function itemOrNull(string|int|null $key): Value {
        return $key === null ? new NullValue() : $this->item($key) ?? new NullValue();
    }
}
