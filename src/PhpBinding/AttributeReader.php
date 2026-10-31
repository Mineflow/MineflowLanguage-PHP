<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Closure;
use Mineflow\Language\Attribute\Entries;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Index;
use Mineflow\Language\Attribute\MemberAttribute;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Definition\Declaration;
use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Definition\MemberKind;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Definition\Parameter;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Error\InternalError;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\NamedType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\Value;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

/**
 * @phpstan-import-type Extractor from PhpMethodCall
 */
final readonly class AttributeReader {
    private PhpTypes $phpTypes;

    public function __construct(private DeclaredTypes $declared) {
        $this->phpTypes = new PhpTypes($declared->objectTypes);
    }

    /**
     * @param list<object> $extensions
     * @param list<object> $functions
     * @return list<Declaration>
     */
    public function read(array $extensions, array $functions): array {
        $declarations = [];
        foreach ($this->declared->declarers as [$type, $declarer]) {
            $declarations[] = $this->declarationsOf($type, $declarer);
        }

        foreach ($extensions as $extension) {
            $extended = ($this->attribute(new ReflectionClass($extension), ExtendType::class)
                ?? throw new DefinitionError("Missing #[ExtendType] on ".$extension::class))->type;
            $ownerType = $extended instanceof Builtin
                ? $extended->type()
                : $this->declared->objectTypes->find($extended) ?? throw new DefinitionError("Unregistered extension target: $extended");

            $declarations[] = $this->declarationsOf($ownerType, $extension);
        }

        foreach ($functions as $functionProvider) {
            $declarations[] = $this->declarationsOf(null, $functionProvider);
        }

        return array_merge(...$declarations);
    }

    /**
     * @return list<Declaration>
     */
    private function declarationsOf(NamedType|CollectionType|null $ownerType, object $instance): array {
        $class = new ReflectionClass($instance);
        $global = $ownerType === null;
        $typeName = $ownerType?->name;

        $declarations = [];
        foreach ($class->getMethods() as $method) {
            $attributes = $this->memberAttributes($method);
            if ($attributes === []) {
                continue;
            }
            if (!$method->isPublic() || $method->isStatic()) {
                throw new DefinitionError("An exposed member must be a public instance method: "
                    .$class->getName()."::".$method->getName());
            }

            $parameters = $method->getParameters();

            /** @var list<Extractor> $extractors */
            $extractors = [];
            if (!$global) {
                $first = array_shift($parameters) ?? throw new DefinitionError("A type member needs a parameter for the target value.");
                $extractors[] = $this->targetExtractor($first, $ownerType);
            }

            /** @var list<Parameter> $definitions */
            $definitions = [];
            foreach ($parameters as $parameter) {
                $extractors[] = $this->parameterExtractor($parameter, $ownerType, $definitions);
            }

            foreach ($attributes as $attribute) {
                $references = $this->references($attribute, $typeName, $method->getName());
                $kind = $references[0]->kind;
                $this->checkParameters($kind, $definitions);

                $declaringClass = $method->getDeclaringClass()->getName();
                $phpType = $method->getReturnType() ?? throw new DefinitionError("Missing PHP return type in $declaringClass");
                $returns = $this->phpTypes->infer($phpType, $attribute->type, $declaringClass, $ownerType);

                $memberParameters = $kind->takesArguments() ? $definitions : [];
                $definition = new MemberDefinition($returns, $memberParameters, $attribute->description);
                $call = new PhpMethodCall($definition, $instance, $method->getName(), $extractors);

                foreach ($references as $reference) {
                    $declarations[] = new Declaration($reference, $definition, $call);
                }
            }
        }

        return $declarations;
    }

    /**
     * @return list<MemberAttribute>
     */
    private function memberAttributes(ReflectionMethod $method): array {
        return array_map(
            static fn(ReflectionAttribute $attribute) => $attribute->newInstance(),
            $method->getAttributes(MemberAttribute::class, ReflectionAttribute::IS_INSTANCEOF),
        );
    }

    /**
     * Returns references to all members the attributes declare. A null $typeName means the declarations of a class added as functions.
     * @return non-empty-list<MemberReference>
     */
    private function references(MemberAttribute $attribute, ?string $typeName, string $method): array {
        if ($typeName === null) {
            if (!$attribute instanceof Method) {
                throw new DefinitionError("A class added with addFunctions() can only declare #[Method].");
            }

            return array_map(MemberReference::function(...), $attribute->names($method));
        }

        return match (true) {
            $attribute instanceof Property => array_map(static fn(string $name) => MemberReference::property($typeName, $name), $attribute->names($method)),
            $attribute instanceof Method => array_map(static fn(string $name) => MemberReference::method($typeName, $name), $attribute->names($method)),
            $attribute instanceof Operator => [$attribute->reflected
                ? MemberReference::reflectedOperator($typeName, $attribute->operator)
                : MemberReference::operator($typeName, $attribute->operator)],
            $attribute instanceof Index => [MemberReference::index($typeName)],
            $attribute instanceof Entries => [MemberReference::entries($typeName)],
        };
    }

    /**
     * @param list<Parameter> $parameters
     */
    private function checkParameters(MemberKind $kind, array $parameters): void {
        if ($kind->takesArguments()) {
            return;
        }

        foreach ($parameters as $parameter) {
            if (!$parameter->optional) {
                throw new DefinitionError("No language arguments for {$kind->value}.");
            }
        }
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
     * @param list<Parameter> $definitions
     * @return Extractor
     */
    private function parameterExtractor(ReflectionParameter $parameter, ?Type $ownerType, array &$definitions): Closure {
        $phpType = $parameter->getType() ?? throw new DefinitionError("Missing PHP type for {$parameter->getName()}");
        if ($parameter->isPassedByReference()) {
            throw new DefinitionError("By-reference language arguments are unsupported.");
        }

        if ($this->attribute($parameter, FromContext::class) !== null) {
            if (!$phpType instanceof ReflectionNamedType || $phpType->getName() !== EvaluationContext::class || $parameter->isVariadic()) {
                throw new DefinitionError("#[FromContext] requires an EvaluationContext parameter.");
            }
            return static fn(?Value $value, array $arguments, EvaluationContext $context): array => [$context];
        }

        $declaredType = $this->attribute($parameter, Param::class)?->type;
        $type = $this->phpTypes->infer($phpType, $declaredType, $this->declaringClass($parameter), $ownerType);

        $index = count($definitions);
        $definitions[] = new Parameter($parameter->getName(), $type, $parameter->isOptional(), $parameter->isVariadic());

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
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionProperty|ReflectionParameter $reflection
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private function attribute(ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter $reflection, string $attributeClass): ?object {
        return ($reflection->getAttributes($attributeClass)[0] ?? null)?->newInstance();
    }
}
