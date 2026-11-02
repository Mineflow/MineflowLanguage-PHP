<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Closure;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Index;
use Mineflow\Language\Attribute\MemberAttribute;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Definition\Declaration;
use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Runtime\Member;
use Mineflow\Language\Runtime\MemberImplementation;
use Mineflow\Language\Runtime\MemberTable;
use Mineflow\Language\Types\DeclaredType;
use Mineflow\Language\Types\TypeRegistry;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

final readonly class AttributeReader {
    private PhpTypes $phpTypes;
    private SignatureReader $signatures;

    private function __construct(private TypeRegistry $types) {
        $this->phpTypes = new PhpTypes($types);
        $this->signatures = new SignatureReader($this->phpTypes);
    }

    /**
     * Reads the members that attributes declare: on the Value classes and interfaces of declared types, on the
     * types #[ExtendType] extensions name, and as functions.
     * @param list<class-string> $types Classes and interfaces with #[DeclareType].
     * @param list<object> $extensions
     * @param list<object> $functions
     */
    public static function read(array $types, array $extensions, array $functions): MemberTable {
        $registered = new TypeRegistry($types);
        return MemberTable::from($registered, (new self($registered))->members($types, $extensions, $functions));
    }

    /**
     * @param list<class-string> $types
     * @param list<object> $extensions
     * @param list<object> $functions
     * @return list<Member>
     */
    private function members(array $types, array $extensions, array $functions): array {
        $members = [];
        foreach ($types as $class) {
            $members[] = $this->valueClassMembers($this->types->of($class), new ReflectionClass($class));
        }

        foreach ($extensions as $extension) {
            $extended = (((new ReflectionClass($extension))->getAttributes(ExtendType::class)[0] ?? null)?->newInstance()
                ?? throw new DefinitionError("Missing #[ExtendType] on ".$extension::class))->type;
            $ownerType = $this->types->find($extended) ?? throw new DefinitionError("Unregistered extension target: $extended");

            $members[] = $this->objectMembers($ownerType, $extension);
        }

        foreach ($functions as $functionProvider) {
            $members[] = $this->objectMembers(null, $functionProvider);
        }

        return array_merge(...$members);
    }

    /**
     * Reads the members the class of a declared type declares, called on the value itself: its own, and those of
     * parent classes that declare no type themselves.
     * @param ReflectionClass<object> $class
     * @return list<Member>
     */
    private function valueClassMembers(DeclaredType $type, ReflectionClass $class): array {
        $members = [];
        foreach ($this->exposedMethods($class) as [$method, $attributes]) {
            $declaringClass = $method->getDeclaringClass()->getName();
            if ($declaringClass !== $class->getName() && $this->types->find($declaringClass) !== null) {
                continue;
            }

            $signature = $this->signatures->readValueMethod($method, $type);
            $members[] = $this->declare($method, $attributes, $type, $signature,
                static fn(MemberDefinition $definition) => new ValueMethodCall($definition, $method->getName(), $signature->extractors));
        }

        return array_merge(...$members);
    }

    /**
     * Reads the members of an object added to the language: an extension of $ownerType, or functions when it is null.
     * @return list<Member>
     */
    private function objectMembers(?DeclaredType $ownerType, object $object): array {
        $members = [];
        foreach ($this->exposedMethods(new ReflectionClass($object)) as [$method, $attributes]) {
            $signature = $this->signatures->readObjectMethod($method, $ownerType);
            $members[] = $this->declare($method, $attributes, $ownerType, $signature,
                static fn(MemberDefinition $definition) => new ObjectMethodCall($definition, $object, $method->getName(), $signature->extractors));
        }

        return array_merge(...$members);
    }

    /**
     * The public methods of the class that declare members, with their member attributes.
     * @param ReflectionClass<object> $class
     * @return list<array{ReflectionMethod, non-empty-list<MemberAttribute>}>
     */
    private function exposedMethods(ReflectionClass $class): array {
        $exposed = [];
        foreach ($class->getMethods() as $method) {
            $attributes = $this->memberAttributes($method);
            if ($attributes === []) {
                continue;
            }
            if (!$method->isPublic()) {
                throw new DefinitionError("An exposed member must be a public method: "
                    .$class->getName()."::".$method->getName());
            }

            $exposed[] = [$method, $attributes];
        }

        return $exposed;
    }

    /**
     * Declares the members the attributes of a method name, each implemented by the call $implement makes.
     * @param non-empty-list<MemberAttribute> $attributes
     * @param Closure(MemberDefinition): MemberImplementation $implement
     * @return list<Member>
     */
    private function declare(ReflectionMethod $method, array $attributes, ?DeclaredType $ownerType, MethodSignature $signature, Closure $implement): array {
        $declaringClass = $method->getDeclaringClass()->getName();
        $members = [];
        foreach ($attributes as $attribute) {
            $references = $this->references($attribute, $ownerType?->name, $method->getName());

            $returns = $this->phpTypes->infer($method->getReturnType(), $attribute->type, $declaringClass, $ownerType);

            $definition = new MemberDefinition($returns, $signature->parametersFor($references[0]->kind), $attribute->description);
            $call = $implement($definition);

            foreach ($references as $reference) {
                $members[] = new Member(new Declaration($reference, $definition), $call);
            }
        }

        return $members;
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
        };
    }
}
