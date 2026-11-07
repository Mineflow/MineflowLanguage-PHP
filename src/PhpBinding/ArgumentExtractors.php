<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Closure;
use Mineflow\Language\Diagnostics\Notices;
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
     * @param ReflectionType|null $phpType
     * @return Extractor
     */
    public function forTarget(?ReflectionType $phpType): Closure {
        $convert = $this->unwrapper($phpType);
        return static function (?Value $value, array $arguments, EvaluationContext $context) use ($convert): array {
            assert($value !== null);
            return [$convert($value, $context->notices())];
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
    public function forArgument(ReflectionParameter $parameter, ?ReflectionType $phpType, int $index): Closure {
        $convert = $this->unwrapper($phpType);
        if ($parameter->isVariadic()) {
            /** @param list<Value> $arguments */
            return static fn(?Value $value, array $arguments, EvaluationContext $context): array => array_map(
                static fn(Value $argument) => $convert($argument, $context->notices()),
                array_values(array_slice($arguments, $index)),
            );
        }

        if ($parameter->isDefaultValueAvailable()) {
            $default = $parameter->getDefaultValue();
            return static fn(?Value $value, array $arguments, EvaluationContext $context): array => [isset($arguments[$index]) ? $convert($arguments[$index], $context->notices()) : $default];
        }

        return static fn(?Value $value, array $arguments, EvaluationContext $context): array => [$convert($arguments[$index], $context->notices())];
    }

    /**
     * Returns a function that passes a language value to a PHP parameter of this type. A parameter of Value classes
     * gets the value as is, or PHP null when it is of none of them; others, untyped ones included, get the PHP value.
     * @return Closure(Value, Notices): mixed
     */
    private function unwrapper(?ReflectionType $phpType): Closure {
        $types = $phpType instanceof ReflectionUnionType ? $phpType->getTypes() : array_filter([$phpType]);
        $names = array_map(static fn(ReflectionType $type) => $type instanceof ReflectionNamedType ? $type->getName() : "", $types);

        $valueClasses = array_values(array_filter($names, static fn(string $name) => is_a($name, Value::class, true)));
        if ($valueClasses !== [] && count($valueClasses) === count(array_diff($names, ["null"]))) {
            return static function (Value $value, Notices $notices) use ($valueClasses): ?Value {
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

        return static function (Value $value, Notices $notices) use ($integerOnly, $takesArray): mixed {
            if ($takesArray && $value instanceof CollectionValue) {
                return $value->toArray();
            }

            $native = $value->native();

            if ($integerOnly && is_float($native)) {
                $integer = match (true) {
                    !is_finite($native) => 0,
                    $native >= (float)PHP_INT_MAX => PHP_INT_MAX,
                    $native <= (float)PHP_INT_MIN => PHP_INT_MIN,
                    default => (int)$native,
                };
                if ((float)$integer !== $native) {
                    $notices->add("$value is not a whole number, so $integer is used.");
                }
                return $integer;
            }

            return $native;
        };
    }
}
