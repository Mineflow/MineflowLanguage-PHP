<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Types\Type;

final readonly class ListValue extends CollectionValue {
    /**
     * @param list<Value> $items
     */
    public function __construct(private array $items) {
        parent::__construct(Type::list(Type::union(...array_map(static fn(Value $v) => $v->type, $items))));
    }

    /**
     * @return list<Value>
     */
    public function items(): array {
        return $this->items;
    }

    public function append(Value $item): self {
        return new self([...$this->items, $item]);
    }

    public function with(string|int $key, Value $item): self {
        if (!array_key_exists($key, $this->items)) {
            throw new InvalidValue("The list has no item at $key; append() adds one at its end.");
        }

        $items = $this->items;
        $items[$key] = $item;
        return new self(array_values($items));
    }

    public function without(string|int $key): static {
        $items = $this->items;
        unset($items[$key]);
        return new self(array_values($items));
    }
}
