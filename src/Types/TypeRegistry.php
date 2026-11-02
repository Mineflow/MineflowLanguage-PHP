<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

use Mineflow\Language\Attribute\FromNative;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Values\TypeDeclarations;
use Mineflow\Language\Values\Value;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * The types a language has: those its Value classes and interfaces declare, and the PHP values their #[FromNative]
 * methods convert.
 */
final readonly class TypeRegistry {
    /** The PHP types other than classes that a #[FromNative] method can convert. An array becomes a list or a map. */
    private const array NATIVE_TYPES = ["string", "int", "float", "bool", "null"];

    /** @var array<class-string, DeclaredType> */
    private array $typesByClass;
    /** @var array<string, class-string> */
    private array $classesByName;
    /** @var array<string, list<DeclaredType>> */
    private array $ancestors;
    /** @var array<string, array{class-string, string}> The Value class and its method that convert each PHP type. */
    private array $converters;

    /**
     * @param list<class-string> $classes Value classes and interfaces with #[DeclareType].
     */
    public function __construct(array $classes) {
        $byClass = [];
        $byName = [];
        foreach ($classes as $class) {
            $type = TypeDeclarations::declaredBy($class) ?? throw new DefinitionError("Missing #[DeclareType] on $class");
            if (isset($byName[$type->name])) {
                throw new DefinitionError("Duplicate type: $type is already declared for {$byName[$type->name]}."
                    ." Use #[ExtendType({$byName[$type->name]}::class)] to add members to it, or give this type another name.");
            }

            if (isset($byClass[$class])) {
                throw new DefinitionError("$class is already represented by the type {$byClass[$class]}");
            }

            $byClass[$class] = $type;
            $byName[$type->name] = $class;
        }

        $this->typesByClass = $byClass;
        $this->classesByName = $byName;
        $interfaces = self::bySpecificity(array_filter($byClass, static fn(string $class) => interface_exists($class), ARRAY_FILTER_USE_KEY));

        $ancestors = [];
        foreach ($byClass as $class => $type) {
            $parents = [];
            for ($parent = get_parent_class($class); $parent !== false; $parent = get_parent_class($parent)) {
                if (isset($byClass[$parent])) {
                    $parents[] = $byClass[$parent];
                }
            }

            $implemented = array_filter($interfaces, static fn(string $interface) => is_subclass_of($class, $interface), ARRAY_FILTER_USE_KEY);
            $ancestors[$type->name] = [...$parents, ...array_values($implemented)];
        }
        $this->ancestors = $ancestors;
        $this->converters = self::readConverters(array_keys($byClass));
    }

    /**
     * @param class-string $class
     */
    public function of(string $class): DeclaredType {
        return $this->typesByClass[$class] ?? throw new DefinitionError("Unregistered PHP type: $class");
    }

    public function find(string $class): ?DeclaredType {
        return $this->typesByClass[$class] ?? null;
    }

    /**
     * Returns the type of that name, or null when none is declared.
     */
    public function named(string $name): ?DeclaredType {
        $class = $this->classesByName[$name] ?? null;
        return $class === null ? null : $this->typesByClass[$class];
    }

    /**
     * @return list<DeclaredType>
     */
    public function ancestors(string $typeName): array {
        return $this->ancestors[$typeName] ?? [];
    }

    /**
     * @return list<string>
     */
    public function names(): array {
        return array_keys($this->classesByName);
    }

    /**
     * The type of the values a PHP value of the PHP type converts to, or null when no #[FromNative] method converts it.
     * @param string $phpType A PHP type other than a class, or a class, which the nearest parent class's conversion takes.
     */
    public function typeForPhpType(string $phpType): ?DeclaredType {
        $converter = $this->converterFor($phpType);
        return $converter === null ? null : $this->of($converter[0]);
    }

    /**
     * Converts the PHP value with the #[FromNative] method for its PHP type, or returns null when there is none.
     */
    public function convertNative(mixed $value): ?Value {
        $converter = $this->converterFor(match (true) {
            is_object($value) => $value::class,
            is_string($value) => "string",
            is_int($value) => "int",
            is_float($value) => "float",
            is_bool($value) => "bool",
            $value === null => "null",
            default => "",
        });
        if ($converter === null) {
            return null;
        }

        [$class, $method] = $converter;
        $converted = $class::$method($value);
        return $converted instanceof Value ? $converted : null;
    }

    /**
     * @return array{class-string, string}|null
     */
    private function converterFor(string $phpType): ?array {
        if (!class_exists($phpType) && !interface_exists($phpType)) {
            return $this->converters[$phpType] ?? null;
        }

        foreach ([$phpType, ...array_values(class_parents($phpType) ?: []), ...array_values(class_implements($phpType) ?: [])] as $class) {
            if (isset($this->converters[$class])) {
                return $this->converters[$class];
            }
        }
        return null;
    }

    /**
     * Reads the #[FromNative] methods the classes declare themselves.
     * @param list<class-string> $classes
     * @return array<string, array{class-string, string}>
     */
    private static function readConverters(array $classes): array {
        $converters = [];
        foreach ($classes as $class) {
            foreach ((new ReflectionClass($class))->getMethods() as $method) {
                if ($method->getAttributes(FromNative::class) === [] || $method->getDeclaringClass()->getName() !== $class) {
                    continue;
                }

                foreach (self::convertedTypes($method) as $phpType) {
                    if (isset($converters[$phpType])) {
                        throw new DefinitionError("Both {$converters[$phpType][0]}::{$converters[$phpType][1]}() and $class::{$method->getName()}() convert the PHP type $phpType");
                    }

                    $converters[$phpType] = [$class, $method->getName()];
                }
            }
        }

        return $converters;
    }

    /**
     * Checks the shape of a #[FromNative] method, and returns the PHP types it converts.
     * @return list<string>
     */
    private static function convertedTypes(ReflectionMethod $method): array {
        $methodName = $method->getDeclaringClass()->getName()."::".$method->getName()."()";
        $parameters = $method->getParameters();
        if (!$method->isPublic() || !$method->isStatic() || count($parameters) !== 1 || $parameters[0]->isVariadic() || $parameters[0]->isPassedByReference()) {
            throw new DefinitionError("#[FromNative] goes on a public static method with one parameter: $methodName");
        }

        $returns = $method->getReturnType();
        $returnClass = $returns instanceof ReflectionNamedType && !$returns->allowsNull() ? $returns->getName() : "";
        $returnClass = $returnClass === "self" ? $method->getDeclaringClass()->getName() : $returnClass;
        if (!is_a($returnClass, $method->getDeclaringClass()->getName(), true)) {
            throw new DefinitionError("A #[FromNative] method returns a value of its own class: $methodName");
        }

        $parameterType = $parameters[0]->getType();
        $alternatives = $parameterType instanceof ReflectionUnionType ? $parameterType->getTypes() : [$parameterType];
        $phpTypes = [];
        foreach ($alternatives as $type) {
            $name = $type instanceof ReflectionNamedType ? $type->getName() : "";
            $isClass = (class_exists($name) || interface_exists($name)) && !is_a($name, Value::class, true);
            if (!$isClass && !in_array($name, self::NATIVE_TYPES, true)) {
                throw new DefinitionError("A #[FromNative] method converts PHP values of a class or of "
                    .implode(", ", self::NATIVE_TYPES).", not $type: $methodName");
            }

            $phpTypes[] = $name;
        }
        if ($parameterType?->allowsNull() === true && !in_array("null", $phpTypes, true)) {
            throw new DefinitionError("A #[FromNative] method converts PHP values of one type, so its parameter is not nullable: $methodName");
        }

        return $phpTypes;
    }

    /**
     * Orders interfaces by inheritance: those extending another interface come first, the rest in declaration order.
     * @param array<class-string, DeclaredType> $interfaces In declaration order.
     * @return array<class-string, DeclaredType>
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
