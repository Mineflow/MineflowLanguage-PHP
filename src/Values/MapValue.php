<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\DeclaredType;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Types\Special;

#[DeclareType("map")]
readonly class MapValue extends CollectionValue {
    private CollectionType $type;

    /**
     * @param array<string|int, Value> $items
     */
    public function __construct(private array $items = []) {
        $this->type = TypeDeclarations::ofCollection($this);
    }

    /**
     * @return array<string|int, Value>
     */
    final public function items(): array {
        return $this->items;
    }

    final public function type(): DeclaredType {
        return $this->type;
    }

    public function with(string|int $key, Value $item): self {
        $items = $this->items;
        $items[$key] = $item;
        return new self($items);
    }

    public function without(string|int $key): self {
        $items = $this->items;
        unset($items[$key]);
        return new self($items);
    }

    public function __toString(): string {
        return "<".implode(",", array_map(
            static fn(string|int $key, Value $item) => $key.":".$item,
            array_keys($this->items),
            array_values($this->items),
        )).">";
    }

    #[Method(type: new MapOf(Placeholder::Element, Placeholder::Key), aliases: ["reversed"], description: "The entries in reverse order.")]
    public function reverse(): self {
        return new self(array_reverse($this->items, true));
    }

    #[Method(type: new MapOf(Placeholder::Element, Placeholder::Key), description: "The first amount entries.")]
    public function take(int $amount): self {
        return new self(array_slice($this->items, 0, $amount, true));
    }

    #[Method(name: "take_last", type: new MapOf(Placeholder::Element, Placeholder::Key), aliases: ["takeLast"], description: "The last amount entries.")]
    public function takeLast(int $amount): self {
        return new self(array_slice($this->items, max(count($this->items) - $amount, 0), null, true));
    }

    #[Method(type: new MapOf(Placeholder::Element, NumberValue::class), aliases: ["shuffled"], description: "The values in random order, keyed by their new position.")]
    public function shuffle(): self {
        $items = array_values($this->items);
        shuffle($items);
        return new self($items);
    }

    #[Operator(BinaryOperator::Add, type: new MapOf(Special::Mixed, Placeholder::Key), description: "Every value with the right operand added to it.")]
    public function add(#[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): self {
        return $this->each(BinaryOperator::Add, $operand, $context);
    }

    #[Operator(BinaryOperator::Subtract, type: new MapOf(Special::Mixed, Placeholder::Key), description: "Every value with the right operand subtracted from it.")]
    public function subtract(#[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): self {
        return $this->each(BinaryOperator::Subtract, $operand, $context);
    }

    #[Operator(BinaryOperator::Multiply, type: new MapOf(Special::Mixed, Placeholder::Key), description: "Every value multiplied by the right operand.")]
    public function multiply(#[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): self {
        return $this->each(BinaryOperator::Multiply, $operand, $context);
    }

    #[Operator(BinaryOperator::Divide, type: new MapOf(Special::Mixed, Placeholder::Key), description: "Every value divided by the right operand.")]
    public function divide(#[Param(Special::Mixed)] Value $operand, #[FromContext] EvaluationContext $context): self {
        return $this->each(BinaryOperator::Divide, $operand, $context);
    }

    private function each(BinaryOperator $operator, Value $operand, EvaluationContext $context): self {
        if ($operand instanceof CollectionValue) {
            throw new UnsupportedCalculation($operator, $this->type(), $operand->type());
        }

        return new self(array_map(static fn(Value $item) => $context->operate($operator, $item, $operand), $this->items));
    }
}
