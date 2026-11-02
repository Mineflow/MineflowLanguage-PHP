<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Types\TypeExpressions;
use Mineflow\Language\Types\TypeRegistry;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\Value;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final readonly class PhpTypes {
    public function __construct(private TypeRegistry $types) {
    }

    /**
     * @param TypeExpression|class-string|null $declaredType
     */
    public function infer(ReflectionType $phpType, TypeExpression|string|null $declaredType, string $declaringClass, ?Type $targetType = null): Type {
        $isValueObject = $phpType instanceof ReflectionNamedType && $phpType->getName() === Value::class;
        $isArray = false;

        $parts = $phpType instanceof ReflectionUnionType ? $phpType->getTypes() : [$phpType];
        foreach ($parts as $part) {
            $isArray = $isArray || ($part instanceof ReflectionNamedType && $part->getName() === "array");
        }

        if ($declaredType !== null) {
            $type = TypeExpressions::resolve($declaredType, new MemberTypeResolver($this->types, $targetType));

            if (!$isValueObject) {
                $phpEquivalent = $this->fromPhpType($phpType, $declaringClass);
                $resolvedType = $type->resolvePlaceholders($targetType);

                if (!$phpEquivalent->accepts($resolvedType)) {
                    throw new DefinitionError("$type conflicts with PHP type $phpType in $declaringClass");
                }
                if (!$isArray && $type->equals($phpEquivalent)) {
                    throw new DefinitionError("The PHP type $phpType already says $type in $declaringClass; leave the language type out");
                }
            }

            return $type;
        }

        if ($isValueObject) {
            throw new DefinitionError("Specify the language type for Value in $declaringClass");
        }
        if ($isArray) {
            throw new DefinitionError("Specify a list/map element type with #[Param(type: ...)] in $declaringClass");
        }

        return $this->fromPhpType($phpType, $declaringClass);
    }

    private function fromPhpType(ReflectionType $phpType, string $declaringClass): Type {
        if ($phpType instanceof ReflectionUnionType) {
            return Type::union(...array_map(fn(ReflectionType $type) => $this->fromPhpType($type, $declaringClass), $phpType->getTypes()));
        }
        if (!$phpType instanceof ReflectionNamedType) {
            throw new DefinitionError("Unsupported PHP type $phpType in $declaringClass");
        }

        $name = match ($phpType->getName()) {
            "self", "static" => $declaringClass,
            "true", "false" => "bool",
            "array" => CollectionValue::class,
            default => $phpType->getName(),
        };
        $type = match (true) {
            $name === "mixed" => Type::mixed(),
            $name === "never" => Type::never(),
            is_a($name, Value::class, true) => $this->types->of($name),
            default => $this->types->find($name) ?? $this->types->typeForPhpType($name)
                ?? throw new DefinitionError("No language type for the PHP type $name in $declaringClass; declare #[FromNative] on the Value class of its type"),
        };

        return $phpType->allowsNull() && $name !== "null" && $name !== "mixed"
            ? Type::union($type, $this->types->of(NullValue::class))
            : $type;
    }
}
