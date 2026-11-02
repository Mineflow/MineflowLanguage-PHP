<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\Expression\CollectionOf;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Types\Type;

#[DeclareType("list", typeHint: new CollectionOf(key: NumberValue::class))]
readonly class ListValue extends CollectionValue {
    private CollectionType $type;

    /**
     * @param list<Value> $items
     * @param Type|null $element The type every item has, as a hint for an editor; null for items of any type.
     */
    public function __construct(private array $items = [], ?Type $element = null) {
        $this->type = self::hintedType(static::class, $element);
    }

    /**
     * @return list<Value>
     */
    final public function items(): array {
        return $this->items;
    }

    final public function type(): CollectionType {
        return $this->type;
    }

    public function append(Value $item): self {
        return new self([...$this->items, $item], $this->hintFor($item));
    }

    public function with(string|int $key, Value $item): self {
        $items = $this->items;
        if (!array_key_exists($key, $items)) {
            throw new InvalidValue("The list has no item at $key; append() adds one at its end.");
        }

        $items[$key] = $item;
        return new self($items, $this->hintFor($item));
    }

    public function without(string|int $key): self {
        $items = $this->items;
        unset($items[$key]);
        return new self(array_values($items), $this->hint());
    }

    public function __toString(): string {
        return "[".implode(",", array_map(strval(...), $this->items))."]";
    }

    #[Method(type: new ListOf(Placeholder::Element), aliases: ["reversed"], description: "The items in reverse order.")]
    public function reverse(): self {
        return new self(array_reverse($this->items), $this->hint());
    }

    #[Method(type: new ListOf(Placeholder::Element), description: "The first amount items.")]
    public function take(int $amount): self {
        return new self(array_slice($this->items, 0, $amount), $this->hint());
    }

    #[Method(name: "take_last", type: new ListOf(Placeholder::Element), aliases: ["takeLast"], description: "The last amount items.")]
    public function takeLast(int $amount): self {
        return new self(array_slice($this->items, max(count($this->items) - $amount, 0)), $this->hint());
    }

    #[Method(type: new ListOf(Placeholder::Element), aliases: ["shuffled"], description: "The items in random order.")]
    public function shuffle(): self {
        $items = $this->items;
        shuffle($items);
        return new self($items, $this->hint());
    }

    #[Method(description: "Joins the textual form of every item, the form a template would render it in.")]
    public function join(string $separator = ","): string {
        return implode($separator, array_map(strval(...), $this->items));
    }

    #[Operator(BinaryOperator::Add, description: "Every item with the right operand added to it.")]
    public function add(Value $operand, #[FromContext] EvaluationContext $context): self {
        return $this->each(BinaryOperator::Add, $operand, $context);
    }

    #[Operator(BinaryOperator::Subtract, description: "Every item with the right operand subtracted from it.")]
    public function subtract(Value $operand, #[FromContext] EvaluationContext $context): self {
        return $this->each(BinaryOperator::Subtract, $operand, $context);
    }

    #[Operator(BinaryOperator::Multiply, description: "Every item multiplied by the right operand.")]
    public function multiply(Value $operand, #[FromContext] EvaluationContext $context): self {
        return $this->each(BinaryOperator::Multiply, $operand, $context);
    }

    #[Operator(BinaryOperator::Divide, description: "Every item divided by the right operand.")]
    public function divide(Value $operand, #[FromContext] EvaluationContext $context): self {
        return $this->each(BinaryOperator::Divide, $operand, $context);
    }

    protected function withItems(array $items): CollectionValue {
        return new self(array_values($items));
    }

    private function each(BinaryOperator $operator, Value $operand, EvaluationContext $context): self {
        if ($operand instanceof CollectionValue) {
            throw new UnsupportedCalculation($operator, $this->type(), $operand->type());
        }

        return new self(array_map(static fn(Value $item) => $context->operate($operator, $item, $operand), $this->items));
    }
}
