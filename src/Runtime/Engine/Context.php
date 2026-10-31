<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Error\UndefinedMember;
use Mineflow\Language\Format\ValueFormatter;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\ObjectValue;
use Mineflow\Language\Values\Value;
use Mineflow\Language\Values\ValueMapper;
use Stringable;

final readonly class Context implements EvaluationContext {
    public function __construct(
        private Scope              $scope,
        private ValueMapper        $valueMapper,
        private MemberAccess       $memberAccess,
        private OperatorResolution $operatorResolution,
        private MemberInvoker      $invoker,
        private ValueFormatter     $formatter,
    ) {
    }

    public function scope(): Scope {
        return $this->scope;
    }

    public function wrap(mixed $value, ?Type $expected = null): Value {
        return $this->valueMapper->wrap($value, $expected);
    }

    public function callMethod(Value $target, string $name, mixed ...$arguments): Value {
        return $this->memberAccess->callMethod($target, $name, $this->wrapAll($arguments), $this);
    }

    public function callFunction(string $name, mixed ...$arguments): Value {
        return $this->memberAccess->callFunction($name, $this->wrapAll($arguments), $this);
    }

    public function operate(BinaryOperator $operator, Value $left, Value $right): Value {
        return $this->operatorResolution->operate($operator, $left, $right, $this);
    }

    public function property(Value $value, string $name): Value {
        return $this->findProperty($value, $name)
            ?? throw new UndefinedMember(MemberReference::property((string)$value->type, $name));
    }

    public function findProperty(Value $value, string $name): ?Value {
        return $this->memberAccess->find($value, $name, $this);
    }

    public function pluck(CollectionValue $collection, string $name): ?CollectionValue {
        return $this->memberAccess->pluck($collection, $name, $this);
    }

    public function asCollection(Value $value): ?CollectionValue {
        return $this->invoker->entries($value, $this);
    }

    public function text(Value $value): string {
        return ExternalFailure::guard(fn() => $this->formatter->format($this->readable($value)));
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
