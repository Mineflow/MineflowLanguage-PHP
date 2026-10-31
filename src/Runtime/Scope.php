<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Error\UndefinedVariable;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\Value;

/**
 * Names and values that source code refers to. A name not found here is looked up through the parents.
 * A child's value hides the same name without changing the parent's value. Values put in are converted with ValueMapper.
 *
 * childRun() creates a scope holding the variables of one run (such as one run of a recipe).
 * A scope's run is the nearest enclosing scope created by childRun(), including itself. If there is none, it is the outermost scope.
 * Global variables are the values the run scope's parent can refer to. If the run is the outermost scope, they are that scope's own values.
 */
final class Scope {
    /** @var array<string, Value> */
    private array $variables = [];

    /**
     * @param array<string, mixed> $variables
     */
    private function __construct(
        private readonly ValueMapper $valueMapper,
        array                        $variables,
        private readonly ?self       $parent,
        private readonly bool        $isRun,
    ) {
        foreach ($variables as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * @param array<string, mixed> $variables
     */
    public static function root(ValueMapper $valueMapper, array $variables = []): self {
        return new self($valueMapper, $variables, null, false);
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function child(array $variables = []): self {
        return new self($this->valueMapper, $variables, $this, false);
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function childRun(array $variables = []): self {
        return new self($this->valueMapper, $variables, $this, true);
    }

    public function set(string $name, mixed $value): void {
        $this->variables[$name] = $this->valueMapper->wrap($value);
    }

    public function setInRun(string $name, mixed $value): void {
        $run = $this->nearestRun();
        for ($scope = $this; $scope !== $run && $scope !== null; $scope = $scope->parent) {
            unset($scope->variables[$name]);
        }

        $run->variables[$name] = $this->valueMapper->wrap($value);
    }

    public function get(string $name): Value {
        return $this->variables[$name] ?? $this->parent?->get($name)
            ?? throw new UndefinedVariable($name);
    }

    public function has(string $name): bool {
        return isset($this->variables[$name]) || ($this->parent?->has($name) ?? false);
    }

    public function remove(string $name): bool {
        $removed = isset($this->variables[$name]);
        unset($this->variables[$name]);

        return $removed;
    }

    public function removeFromRun(string $name): bool {
        $run = $this->nearestRun();
        $removed = false;
        for ($scope = $this; $scope !== null; $scope = $scope === $run ? null : $scope->parent) {
            $removed = $scope->remove($name) || $removed;
        }

        return $removed;
    }

    /**
     * @return array<string, Value>
     */
    public function all(): array {
        return array_replace($this->parent?->all() ?? [], $this->variables);
    }

    /**
     * @return array<string, Value>
     */
    public function globals(): array {
        $run = $this->nearestRun();
        return $run->parent?->all() ?? $run->variables;
    }

    /**
     * @return array<string, Type>
     */
    public function types(): array {
        return array_map(static fn(Value $v) => $v->type, $this->all());
    }

    private function nearestRun(): self {
        $scope = $this;
        while (!$scope->isRun && $scope->parent !== null) {
            $scope = $scope->parent;
        }

        return $scope;
    }
}
