<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Mineflow\Language\Attribute\Entries;
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
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\NamedType;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

final readonly class AttributeReader {
    private PhpTypes $phpTypes;
    private SignatureReader $signatures;

    public function __construct(private DeclaredTypes $declared) {
        $this->phpTypes = new PhpTypes($declared->objectTypes);
        $this->signatures = new SignatureReader($this->phpTypes);
    }

    /**
     * @param list<object> $extensions
     * @param list<object> $functions
     * @return list<Member>
     */
    public function read(array $extensions, array $functions): array {
        $members = [];
        foreach ($this->declared->declarers as [$type, $declarer]) {
            $members[] = $this->membersOf($type, $declarer);
        }

        foreach ($extensions as $extension) {
            $extended = (((new ReflectionClass($extension))->getAttributes(ExtendType::class)[0] ?? null)?->newInstance()
                ?? throw new DefinitionError("Missing #[ExtendType] on ".$extension::class))->type;
            $ownerType = $extended instanceof Builtin
                ? $extended->type()
                : $this->declared->objectTypes->find($extended) ?? throw new DefinitionError("Unregistered extension target: $extended");

            $members[] = $this->membersOf($ownerType, $extension);
        }

        foreach ($functions as $functionProvider) {
            $members[] = $this->membersOf(null, $functionProvider);
        }

        return array_merge(...$members);
    }

    /**
     * @return list<Member>
     */
    private function membersOf(NamedType|CollectionType|null $ownerType, object $instance): array {
        $class = new ReflectionClass($instance);

        $members = [];
        foreach ($class->getMethods() as $method) {
            $attributes = $this->memberAttributes($method);
            if ($attributes === []) {
                continue;
            }
            if (!$method->isPublic() || $method->isStatic()) {
                throw new DefinitionError("An exposed member must be a public instance method: "
                    .$class->getName()."::".$method->getName());
            }

            $signature = $this->signatures->read($method, $ownerType);
            foreach ($attributes as $attribute) {
                $references = $this->references($attribute, $ownerType?->name, $method->getName());

                $declaringClass = $method->getDeclaringClass()->getName();
                $phpType = $method->getReturnType() ?? throw new DefinitionError("Missing PHP return type in $declaringClass");
                $returns = $this->phpTypes->infer($phpType, $attribute->type, $declaringClass, $ownerType);

                $definition = new MemberDefinition($returns, $signature->parametersFor($references[0]->kind), $attribute->description);
                $call = new PhpMethodCall($definition, $instance, $method->getName(), $signature->extractors);

                foreach ($references as $reference) {
                    $members[] = new Member(new Declaration($reference, $definition), $call);
                }
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
            $attribute instanceof Entries => [MemberReference::entries($typeName)],
        };
    }
}
