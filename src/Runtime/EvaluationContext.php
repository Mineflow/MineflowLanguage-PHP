<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Error\UndefinedMember;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\ObjectValue;
use Mineflow\Language\Values\Value;
use Stringable;

final readonly class EvaluationContext {
    public function __construct(public Scope $scope, private Interpreter $interpreter) {
    }

    /**
     * Converts a PHP value to a language value. $expected decides whether an array becomes a list or a map.
     */
    public function wrap(mixed $value, ?Type $expected = null): Value {
        return $this->interpreter->valueMapper->wrap($value, $expected);
    }

    public function callMethod(Value $target, string $name, mixed ...$arguments): Value {
        return $this->interpreter->memberAccess->callMethod($target, $name, $this->wrapAll($arguments), $this);
    }

    /**
     * Calls `name(arguments)`.
     */
    public function callFunction(string $name, mixed ...$arguments): Value {
        return $this->interpreter->memberAccess->callFunction($name, $this->wrapAll($arguments), $this);
    }

    /**
     * Calculates `left symbol right`.
     */
    public function operate(BinaryOperator $operator, Value $left, Value $right): Value {
        return $this->interpreter->operatorResolution->operate($operator, $left, $right, $this);
    }

    public function property(Value $value, string $name): Value {
        return $this->findProperty($value, $name)
            ?? throw new UndefinedMember(MemberReference::property((string)$value->type, $name));
    }

    /**
     * Reads `value.name` like property(), or null when no meaning of the name applies.
     */
    public function findProperty(Value $value, string $name): ?Value {
        return $this->interpreter->memberAccess->find($value, $name, $this);
    }

    /**
     * Gets `name` from each element of the collection, even if the collection itself has `name`.
     * Returns null if any element has no `name`.
     */
    public function pluck(CollectionValue $collection, string $name): ?CollectionValue {
        return $this->interpreter->memberAccess->pluck($collection, $name, $this);
    }

    /**
     * Returns the list or map the value acts as, or null when it acts as neither.
     */
    public function asCollection(Value $value): ?CollectionValue {
        return $this->interpreter->memberAccess->entries($value, $this);
    }

    public function text(Value $value): string {
        return ExternalFailure::guard(fn() => $this->interpreter->formatter->format($this->readable($value)));
    }

    private function readable(Value $value): Value {
        if ($value instanceof CollectionValue) {
            $items = array_map($this->readable(...), $value->items());
            return $value instanceof ListValue ? new ListValue(array_values($items)) : new MapValue($items);
        }

        if ($value instanceof ObjectValue && $value->value instanceof Stringable) {
            return $value;
        }

        $collection = $this->asCollection($value);
        return $collection === null ? $value : $this->readable($collection);
    }

    /**
     * @param array<mixed> $arguments
     * @return list<Value>
     */
    private function wrapAll(array $arguments): array {
        return array_values(array_map($this->wrap(...), $arguments));
    }
}
