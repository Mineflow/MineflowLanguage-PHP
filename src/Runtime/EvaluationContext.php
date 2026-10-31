<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\Value;

/**
 * What a member that takes #[FromContext] can do: read the scope of the source being evaluated, and work with values
 * the way a source does. An argument may be any PHP value a scope takes.
 */
interface EvaluationContext {
    public function scope(): Scope;

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
     * Reads `value.name`.
     */
    public function property(Value $value, string $name): Value;

    /**
     * Reads `value.name` like property(), or null when no meaning of the name applies.
     */
    public function findProperty(Value $value, string $name): ?Value;

    /**
     * Gets `name` from each element of the collection, even if the collection itself has `name`.
     * Returns null if any element has no `name`.
     */
    public function pluck(CollectionValue $collection, string $name): ?CollectionValue;

    /**
     * Returns the list or map the value acts as, or null when it acts as neither.
     */
    public function asCollection(Value $value): ?CollectionValue;

    /**
     * Writes the value as text, the way a template writes it.
     */
    public function text(Value $value): string;
}
