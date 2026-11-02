<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Closure;
use Mineflow\Language\Error\InternalError;
use Mineflow\Language\Error\InvalidIntegerConversion;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\Value;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * @phpstan-type Extractor Closure(?Value, list<Value>, EvaluationContext): list<mixed>
 */
final readonly class ArgumentExtractors {
    /**
     * @return Extractor
     */
    public function forTarget(ReflectionType $phpType): Closure {
        $convert = $this->unwrapper($phpType);
        return static function (?Value $value, array $arguments, EvaluationContext $context) use ($convert): array {
            if ($value === null) {
                throw new InternalError("Missing the target value of a type member.");
            }

            return [$convert($value)];
        };
    }

    /**
     * @return Extractor
     */
    public function forContext(): Closure {
        return static fn(?Value $value, array $arguments, EvaluationContext $context): array => [$context];
    }

    /**
     * @param int $index The position of the language argument the parameter receives.
     * @return Extractor
     */
    public function forArgument(ReflectionParameter $parameter, ReflectionType $phpType, int $index): Closure {
        $convert = $this->unwrapper($phpType);
        if ($parameter->isVariadic()) {
            /** @param list<Value> $arguments */
            return static fn(?Value $value, array $arguments, EvaluationContext $context): array => array_map($convert, array_values(array_slice($arguments, $index)));
        }

        if ($parameter->isDefaultValueAvailable()) {
            $default = $parameter->getDefaultValue();
            return static fn(?Value $value, array $arguments, EvaluationContext $context): array => [isset($arguments[$index]) ? $convert($arguments[$index]) : $default];
        }

        return static fn(?Value $value, array $arguments, EvaluationContext $context): array => [$convert($arguments[$index])];
    }

    /**
     * Returns a function that passes a language value to a PHP parameter of this type. A parameter of Value classes
     * gets the value as is, or PHP null when it is of none of them; others get the PHP value.
     * @return Closure(Value): mixed
     */
    private function unwrapper(ReflectionType $phpType): Closure {
        $types = $phpType instanceof ReflectionUnionType ? $phpType->getTypes() : [$phpType];
        $names = array_map(static fn(ReflectionType $type) => $type instanceof ReflectionNamedType ? $type->getName() : "", $types);

        $valueClasses = array_values(array_filter($names, static fn(string $name) => is_a($name, Value::class, true)));
        if ($valueClasses !== [] && count($valueClasses) === count(array_diff($names, ["null"]))) {
            return static function (Value $value) use ($valueClasses): ?Value {
                foreach ($valueClasses as $class) {
                    if ($value instanceof $class) {
                        return $value;
                    }
                }
                return null;
            };
        }

        $integerOnly = in_array("int", $names, true) && !in_array("float", $names, true) && !in_array("mixed", $names, true);
        $takesArray = in_array("array", $names, true);

        return static function (Value $value) use ($integerOnly, $takesArray): mixed {
            if ($takesArray && $value instanceof CollectionValue) {
                return $value->toArray();
            }

            $native = $value->native();

            if ($integerOnly && is_float($native)) {
                if (!is_finite($native) || floor($native) !== $native || $native < PHP_INT_MIN || $native >= (float)PHP_INT_MAX) {
                    throw new InvalidIntegerConversion($native);
                }
                return (int)$native;
            }

            return $native;
        };
    }
}
