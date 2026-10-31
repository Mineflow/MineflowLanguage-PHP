<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Types\Type;

final readonly class MapValue extends CollectionValue {
    /**
     * @param array<string|int, Value> $items
     */
    public function __construct(private array $items) {
        parent::__construct(Type::map(
            Type::union(...array_values(array_map(static fn(Value $v) => $v->type, $items))),
            Type::union(...array_map(static fn($key) => is_int($key) ? Type::number() : Type::string(), array_keys($items))),
        ));
    }

    /**
     * @return array<string|int, Value>
     */
    public function items(): array {
        return $this->items;
    }

    public function with(string|int $key, Value $item): self {
        $items = $this->items;
        $items[$key] = $item;
        return new self($items);
    }

    public function without(string|int $key): static {
        $items = $this->items;
        unset($items[$key]);
        return new self($items);
    }
}
