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
use Mineflow\Language\Values\Value;
use Mineflow\Language\Values\ValueMapper;

final readonly class Context implements EvaluationContext {
    public function __construct(
        private Scope              $scope,
        private ValueMapper        $valueMapper,
        private MemberAccess       $memberAccess,
        private OperatorResolution $operatorResolution,
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
            ?? throw new UndefinedMember(MemberReference::property((string)$value->type(), $name));
    }

    public function findProperty(Value $value, string $name): ?Value {
        return $this->memberAccess->find($value, $name, $this);
    }

    public function text(Value $value): string {
        return ExternalFailure::guard(fn() => $this->formatter->format($value));
    }

    /**
     * @param array<mixed> $arguments
     * @return list<Value>
     */
    private function wrapAll(array $arguments): array {
        return array_values(array_map($this->wrap(...), $arguments));
    }
}
