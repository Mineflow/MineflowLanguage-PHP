<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Types\ObjectTypes;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Values\BooleanValue;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final readonly class PhpTypes {
    public function __construct(private ObjectTypes $objectTypes) {
    }

    public function infer(ReflectionType $phpType, ?TypeExpression $declaredType, string $declaringClass, ?Type $targetType = null): Type {
        $isValueObject = $phpType instanceof ReflectionNamedType && $phpType->getName() === Value::class;
        $isArray = false;

        $parts = $phpType instanceof ReflectionUnionType ? $phpType->getTypes() : [$phpType];
        foreach ($parts as $part) {
            $isArray = $isArray || ($part instanceof ReflectionNamedType && $part->getName() === "array");
        }

        if ($declaredType !== null) {
            $type = $declaredType->resolve(new MemberTypeResolver($this->objectTypes, $targetType));

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

        $name = $phpType->getName();
        $type = match ($name) {
            "int", "float", NumberValue::class => Type::number(),
            "bool", "true", "false", BooleanValue::class => Type::boolean(),
            "string", StringValue::class => Type::string(),
            "null", NullValue::class => Type::null(),
            "mixed" => Type::mixed(),
            "never" => Type::never(),
            "array", CollectionValue::class => Type::anyCollection(),
            ListValue::class => Type::list(Type::mixed()),
            MapValue::class => Type::map(Type::mixed()),
            "self", "static" => $this->objectTypes->of($declaringClass),
            default => $this->objectTypes->of($name),
        };

        return $phpType->allowsNull() ? Type::union($type, Type::null()) : $type;
    }
}
