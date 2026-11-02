<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Definition\Parameter;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Types\DeclaredType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\Value;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;

/**
 * @phpstan-import-type Extractor from ArgumentExtractors
 */
final readonly class SignatureReader {
    public function __construct(
        private PhpTypes           $phpTypes,
        private ArgumentExtractors $extractors = new ArgumentExtractors(),
    ) {
    }

    /**
     * Reads the parameters of a method a Value class declares a member with. It is called on the target value, so
     * every parameter is a language argument.
     */
    public function readValueMethod(ReflectionMethod $method, DeclaredType $ownerType): MethodSignature {
        return $this->readArguments($method->getParameters(), [], $ownerType);
    }

    /**
     * Reads the parameters of a method of an object added to the language. A type member's first parameter receives the
     * target value; a null $ownerType means a method added as a function, which has none.
     */
    public function readObjectMethod(ReflectionMethod $method, ?DeclaredType $ownerType): MethodSignature {
        $reflected = $method->getParameters();
        if ($ownerType === null) {
            return $this->readArguments($reflected, [], null);
        }

        $first = array_shift($reflected) ?? throw new DefinitionError("A type member needs a parameter for the target value.");
        return $this->readArguments($reflected, [$this->extractors->forTarget($this->checkTarget($first, $ownerType))], $ownerType);
    }

    /**
     * @param list<ReflectionParameter> $reflected The parameters that take language arguments or the context.
     * @param list<Extractor> $extractors Those of the parameters before them.
     */
    private function readArguments(array $reflected, array $extractors, ?DeclaredType $ownerType): MethodSignature {
        /** @var list<Parameter> $parameters */
        $parameters = [];
        foreach ($reflected as $parameter) {
            $phpType = $parameter->getType() ?? throw new DefinitionError("Missing PHP type for {$parameter->getName()}");
            if ($parameter->isPassedByReference()) {
                throw new DefinitionError("By-reference language arguments are unsupported.");
            }

            if ($this->attribute($parameter, FromContext::class) !== null) {
                if (!$phpType instanceof ReflectionNamedType || $phpType->getName() !== EvaluationContext::class || $parameter->isVariadic()) {
                    throw new DefinitionError("#[FromContext] requires an EvaluationContext parameter.");
                }

                $extractors[] = $this->extractors->forContext();
                continue;
            }

            $declaredType = $this->attribute($parameter, Param::class)?->type;
            $isRawValue = $phpType instanceof ReflectionNamedType && $phpType->getName() === Value::class;

            $extractors[] = $this->extractors->forArgument($parameter, $phpType, count($parameters));
            $parameters[] = new Parameter(
                $parameter->getName(),
                $declaredType === null && $isRawValue ? Type::mixed() : $this->phpTypes->infer($phpType, $declaredType, $this->declaringClass($parameter), $ownerType),
                $parameter->isOptional(),
                $parameter->isVariadic(),
            );
        }

        return new MethodSignature($parameters, $extractors);
    }

    /**
     * Checks that the parameter can receive a target value of the owner type, and returns its PHP type.
     */
    private function checkTarget(ReflectionParameter $parameter, DeclaredType $ownerType): ReflectionType {
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

        return $phpType;
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
