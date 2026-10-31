<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Error\InvalidValue;

final readonly class ObjectTypes {
    /** @var array<class-string, NamedType> */
    private array $types;
    /** @var array<class-string, NamedType> */
    private array $interfaces;
    /** @var array<string, list<NamedType>> */
    private array $ancestors;

    public function __construct(NamedType ...$types) {
        $byClass = [];
        $byName = [];
        foreach ($types as $type) {
            if ($type->class === null) {
                throw new DefinitionError("$type is a built-in type, not one declared for a PHP class.");
            }

            if (isset($byName[$type->name])) {
                throw new DefinitionError("Duplicate type: $type is already declared for {$byName[$type->name]}."
                    ." Use #[ExtendType({$byName[$type->name]}::class)] to add members to it, or give this type another name.");
            }

            if (isset($byClass[$type->class])) {
                throw new DefinitionError("$type->class is already represented by the type {$byClass[$type->class]}");
            }

            $byClass[$type->class] = $type;
            $byName[$type->name] = $type->class;
        }

        $this->types = $byClass;
        $this->interfaces = self::bySpecificity(array_filter($byClass, static fn(string $class) => interface_exists($class), ARRAY_FILTER_USE_KEY));

        $ancestors = [];
        foreach ($byClass as $class => $type) {
            $parents = [];
            for ($parent = get_parent_class($class); $parent !== false; $parent = get_parent_class($parent)) {
                if (isset($byClass[$parent])) {
                    $parents[] = $byClass[$parent];
                }
            }

            $implemented = array_filter($this->interfaces, static fn(string $interface) => is_subclass_of($class, $interface), ARRAY_FILTER_USE_KEY);
            $ancestors[$type->name] = [...$parents, ...array_values($implemented)];
        }
        $this->ancestors = $ancestors;
    }

    public function of(string $class): NamedType {
        return $this->types[$class] ?? throw new DefinitionError("Unregistered PHP type: $class");
    }

    public function find(string $class): ?NamedType {
        return $this->types[$class] ?? null;
    }

    /**
     * Returns the type of an object: the type of its class or nearest parent class, otherwise the type of its most specific interface.
     * It is ambiguous when two unrelated interfaces match, so a type must be declared for the class.
     */
    public function forObject(object $value): ?NamedType {
        for ($class = $value::class; $class !== false; $class = get_parent_class($class)) {
            if (isset($this->types[$class])) {
                return $this->types[$class];
            }
        }

        $implemented = array_filter($this->interfaces, static fn(string $interface) => $value instanceof $interface, ARRAY_FILTER_USE_KEY);
        $specific = array_values(array_filter($implemented, static fn(string $interface) => array_filter(
                array_keys($implemented),
                static fn(string $other) => is_subclass_of($other, $interface),
            ) === [], ARRAY_FILTER_USE_KEY));
        if (count($specific) > 1) {
            throw new InvalidValue($value::class." implements the interfaces of the types ".implode(", ", $specific)
                ." alike; declare a type for its class.");
        }

        return $specific[0] ?? null;
    }

    /**
     * @return list<NamedType>
     */
    public function ancestors(string $typeName): array {
        return $this->ancestors[$typeName] ?? [];
    }

    /**
     * @return list<string>
     */
    public function names(): array {
        return array_values(array_map(static fn(NamedType $type) => $type->name, $this->types));
    }

    /**
     * Orders interfaces by inheritance: those extending another interface come first, the rest in declaration order.
     * @param array<class-string, NamedType> $interfaces In declaration order.
     * @return array<class-string, NamedType>
     */
    private static function bySpecificity(array $interfaces): array {
        $ordered = [];
        while ($interfaces !== []) {
            // Inheritance has no cycles, so an interface not extended by the others is always found.
            foreach ($interfaces as $interface => $type) {
                if (array_filter(array_keys($interfaces), static fn(string $other) => is_subclass_of($other, $interface)) === []) {
                    $ordered[$interface] = $type;
                    unset($interfaces[$interface]);
                    break;
                }
            }
        }

        return $ordered;
    }
}
