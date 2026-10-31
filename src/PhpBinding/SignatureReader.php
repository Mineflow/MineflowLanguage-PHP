<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Closure;
use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Definition\Parameter;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Error\InternalError;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\NamedType;
use Mineflow\Language\Values\Value;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

/**
 * @phpstan-import-type Extractor from PhpMethodCall
 */
final readonly class SignatureReader {
    public function __construct(private PhpTypes $phpTypes) {
    }

    /**
     * Reads the parameters of a method that implements a member. A type member's first parameter receives the target value;
     * a null $ownerType means a method added as a function, which has none.
     */
    public function read(ReflectionMethod $method, NamedType|CollectionType|null $ownerType): MethodSignature {
        $reflected = $method->getParameters();

        /** @var list<Extractor> $extractors */
        $extractors = [];
        if ($ownerType !== null) {
            $first = array_shift($reflected) ?? throw new DefinitionError("A type member needs a parameter for the target value.");
            $extractors[] = $this->targetExtractor($first, $ownerType);
        }

        /** @var list<Parameter> $parameters */
        $parameters = [];
        foreach ($reflected as $parameter) {
            $phpType = $parameter->getType() ?? throw new DefinitionError("Missing PHP type for {$parameter->getName()}");
            if ($parameter->isPassedByReference()) {
                throw new DefinitionError("By-reference language arguments are unsupported.");
            }

            if ($this->attribute($parameter, FromContext::class) !== null) {
                $extractors[] = $this->contextExtractor($parameter, $phpType);
                continue;
            }

            $extractors[] = $this->argumentExtractor($parameter, $phpType, count($parameters));
            $parameters[] = new Parameter(
                $parameter->getName(),
                $this->phpTypes->infer($phpType, $this->attribute($parameter, Param::class)?->type, $this->declaringClass($parameter), $ownerType),
                $parameter->isOptional(),
                $parameter->isVariadic(),
            );
        }

        return new MethodSignature($parameters, $extractors);
    }

    /**
     * @return Extractor
     */
    private function targetExtractor(ReflectionParameter $parameter, NamedType|CollectionType $ownerType): Closure {
        if ($parameter->isPassedByReference() || $parameter->isVariadic() || $this->attribute($parameter, FromContext::class) !== null) {
            throw new DefinitionError("The target value must be the type member's first ordinary parameter.");
        }

        $phpType = $parameter->getType() ?? throw new DefinitionError("Missing PHP type for the target value.");
        $declaredType = $this->attribute($parameter, Param::class)?->type;

        $isRawTarget = $phpType instanceof ReflectionNamedType && in_array($phpType->getName(), [Value::class, "array"], true);
        $targetType = $declaredType === null && $isRawTarget ? $ownerType : $this->phpTypes->infer($phpType, $declaredType, $this->declaringClass($parameter), $ownerType);
        if (!$targetType->resolvePlaceholders($ownerType)->accepts($ownerType)) {
            throw new DefinitionError("The target value of a type member does not accept the type it is declared on.");
        }

        $convert = $this->phpTypes->unwrapperFor($phpType);
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
    private function contextExtractor(ReflectionParameter $parameter, ReflectionType $phpType): Closure {
        if (!$phpType instanceof ReflectionNamedType || $phpType->getName() !== EvaluationContext::class || $parameter->isVariadic()) {
            throw new DefinitionError("#[FromContext] requires an EvaluationContext parameter.");
        }

        return static fn(?Value $value, array $arguments, EvaluationContext $context): array => [$context];
    }

    /**
     * @param int $index The position of the language argument the parameter receives.
     * @return Extractor
     */
    private function argumentExtractor(ReflectionParameter $parameter, ReflectionType $phpType, int $index): Closure {
        $convert = $this->phpTypes->unwrapperFor($phpType);
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

    private function declaringClass(ReflectionParameter $parameter): string {
        return $parameter->getDeclaringClass()?->getName() ?? $parameter->getDeclaringFunction()->getName();
    }

    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private function attribute(ReflectionParameter $parameter, string $attributeClass): ?object {
        return ($parameter->getAttributes($attributeClass)[0] ?? null)?->newInstance();
    }
}
