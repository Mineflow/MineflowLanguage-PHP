<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Index;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Error\NoTextRepresentation;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Types\DeclaredType;
use Mineflow\Language\Types\Expression\CollectionOf;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Types\Expression\Nullable;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Types\Special;
use Stringable;

#[DeclareType("collection")]
abstract readonly class CollectionValue extends Value {
    /**
     * @return array<string|int, Value>
     */
    abstract public function items(): array;

    public function type(): DeclaredType {
        return TypeDeclarations::ofCollection($this);
    }

    public function item(string|int $key): ?Value {
        return $this->items()[$key] ?? null;
    }

    /**
     * The items as a PHP array of their native values.
     * @return array<string|int, mixed>
     */
    public function toArray(): array {
        return array_map(static fn(Value $v) => $v->native(), $this->items());
    }

    /**
     * The items as a PHP array, unless the class gives the PHP value it stands for.
     */
    public function native(): mixed {
        return $this->toArray();
    }

    #[Property(description: "How many items it holds.")]
    #[Method(name: "count", description: "How many items it holds.")]
    public function length(): int {
        return count($this->items());
    }

    #[Method(type: new ListOf(Placeholder::Key), description: "Every key, in order.")]
    public function keys(): ListValue {
        return new ListValue(array_map(
            static fn(string|int $key) => is_int($key) ? new NumberValue($key) : new StringValue($key),
            array_keys($this->items()),
        ));
    }

    #[Method(type: new ListOf(Placeholder::Element), description: "Every item, in order.")]
    public function values(): ListValue {
        return new ListValue(array_values($this->items()));
    }

    #[Method(type: new Nullable(Placeholder::Element), description: "The first item, or null when there is none.")]
    public function first(): Value {
        $items = $this->items();
        return $items === [] ? new NullValue() : $items[array_key_first($items)];
    }

    #[Method(type: new Nullable(Placeholder::Element), description: "The last item, or null when there is none.")]
    public function last(): Value {
        $items = $this->items();
        return $items === [] ? new NullValue() : $items[array_key_last($items)];
    }

    #[Method(name: "first_key", type: new Nullable(Placeholder::Key), description: "The key of the first item, or null when there is none.")]
    public function firstKey(): string|int|null {
        return array_key_first($this->items());
    }

    #[Method(name: "last_key", type: new Nullable(Placeholder::Key), description: "The key of the last item, or null when there is none.")]
    public function lastKey(): string|int|null {
        return array_key_last($this->items());
    }

    #[Method(type: new Nullable(Placeholder::Element), description: "One item picked at random, or null when there is none.")]
    public function random(): Value {
        $items = $this->items();
        return $items === [] ? new NullValue() : $items[array_rand($items)];
    }

    #[Method(type: new Nullable(new CollectionOf(Special::Mixed, Placeholder::Key)), description: "The property of that name read off every item, or null when one item has none.")]
    public function pluck(string $name, #[FromContext] EvaluationContext $context): ?self {
        $items = [];
        foreach ($this->items() as $key => $item) {
            $result = $context->findProperty($item, $name);
            if ($result === null) {
                return null;
            }

            $items[$key] = $result;
        }

        return $this->withItems($items);
    }

    #[Index(type: Special::Mixed, description: "The item under that key, or else the property of that name read off every item; null when neither is there.")]
    public function entry(string $name, #[FromContext] EvaluationContext $context): ?Value {
        return $this->item($name) ?? $this->pluck($name, $context);
    }

    /**
     * A plain collection of these items, the shape the class keeps: a map keeping the keys, unless it is a list.
     * @param array<string|int, Value> $items
     */
    protected function withItems(array $items): self {
        return new MapValue($items);
    }

    protected static function textOf(Value $item): string {
        return $item instanceof Stringable ? (string)$item : throw new NoTextRepresentation($item->type());
    }
}
