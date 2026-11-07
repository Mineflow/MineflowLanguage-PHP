<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\Value;
use Mineflow\Language\Values\ValueMapper;

final readonly class Context implements EvaluationContext {
    public function __construct(
        private Scope              $scope,
        private Notices            $notices,
        private ValueMapper        $valueMapper,
        private MemberAccess       $memberAccess,
        private OperatorResolution $operatorResolution,
    ) {
    }

    public function scope(): Scope {
        return $this->scope;
    }

    public function notices(): Notices {
        return $this->notices;
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
        $found = $this->findProperty($value, $name);
        if ($found !== null) {
            return $found;
        }

        if (!$value instanceof NullValue) {
            $this->notices->add("A value of the type {$value->type()} has no property $name, so null is used.");
        }
        return new NullValue();
    }

    public function findProperty(Value $value, string $name): ?Value {
        return $this->memberAccess->find($value, $name, $this);
    }

    /**
     * @param array<mixed> $arguments
     * @return list<Value>
     */
    private function wrapAll(array $arguments): array {
        return array_values(array_map($this->wrap(...), $arguments));
    }
}
