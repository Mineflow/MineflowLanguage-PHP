<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\Value;

/**
 * What a member that takes #[FromContext] can do: read the scope of the source being evaluated, and work with values
 * the way a source does. An argument may be any PHP value a scope takes.
 */
interface EvaluationContext {
    public function scope(): Scope;

    public function notices(): Notices;

    /**
     * Converts a PHP value to a language value. $expected decides whether an array becomes a list or a map.
     */
    public function wrap(mixed $value, ?Type $expected = null): Value;

    /**
     * Calls `target.name(arguments)`.
     */
    public function callMethod(Value $target, string $name, mixed ...$arguments): Value;

    /**
     * Calls `name(arguments)`.
     */
    public function callFunction(string $name, mixed ...$arguments): Value;

    /**
     * Calculates `left symbol right`.
     */
    public function operate(BinaryOperator $operator, Value $left, Value $right): Value;

    /**
     * Reads `value.name`. When nothing gives the name, the result is null, with a notice unless the value is null.
     */
    public function property(Value $value, string $name): Value;

    /**
     * Reads `value.name` like property(), or null when neither a property nor the index of its type gives the name.
     */
    public function findProperty(Value $value, string $name): ?Value;
}
