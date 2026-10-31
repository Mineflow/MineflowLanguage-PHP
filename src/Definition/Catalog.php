<?php
declare(strict_types=1);

namespace Mineflow\Language\Definition;

use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\NamedType;
use Mineflow\Language\Types\ObjectTypes;

final readonly class Catalog {
    /**
     * @param list<string> $typeNames
     * @param ObjectTypes $objectTypes
     * @param array<string, array<string, array<string, Declaration>>> $members
     * @param array<string, Declaration> $functions
     */
    private function __construct(
        public array       $typeNames,
        public ObjectTypes $objectTypes,
        private array      $members,
        private array      $functions,
    ) {
    }

    /**
     * @param list<Declaration> $declarations
     */
    public static function from(ObjectTypes $objectTypes, array $declarations): self {
        $typeNames = [...array_map(static fn(Builtin $builtin) => $builtin->value, Builtin::cases()), ...$objectTypes->names()];
        $members = [];
        $functions = [];

        foreach ($declarations as $declaration) {
            self::validate($declaration, $typeNames);

            $reference = $declaration->reference;
            $taken = $reference->typeName === null
                ? isset($functions[$reference->name])
                : isset($members[$reference->typeName][$reference->kind->value][$reference->name]);
            if ($taken) {
                throw new DefinitionError("Duplicate {$reference->kind->value}: $reference");
            }

            if ($reference->typeName === null) {
                $functions[$reference->name] = $declaration;
            } else {
                $members[$reference->typeName][$reference->kind->value][$reference->name] = $declaration;
            }
        }

        return new self($typeNames, $objectTypes, $members, $functions);
    }

    /**
     * Returns the member the reference points to. If the type doesn't declare it, returns the one from the nearest ancestor.
     */
    public function declaration(MemberReference $reference): ?Declaration {
        $declared = $this->findInLineage($reference);
        return $declared === null || $reference->typeName === null || $declared->reference->typeName === $reference->typeName
            ? $declared
            : $declared->withTypeName($reference->typeName);
    }

    /**
     * Returns where the referenced member is declared: the ancestor's reference for an inherited member, or null if not found.
     */
    public function origin(MemberReference $reference): ?MemberReference {
        return $this->findInLineage($reference)?->reference;
    }

    /**
     * Returns the information (parameters, return value, description) of the referenced member.
     */
    public function definition(MemberReference $reference): ?MemberDefinition {
        return $this->declaration($reference)?->definition;
    }

    /**
     * @return array<string, MemberDefinition> Keyed by function name.
     */
    public function functions(): array {
        return array_map(static fn(Declaration $declaration) => $declaration->definition, $this->functions);
    }

    /**
     * @return array<string, array<string, MemberDefinition>> All members a value of the type has, keyed by kind, then name.
     *     Members the type declares come first, then inherited ones from the nearest ancestor.
     */
    public function members(string $typeName): array {
        $members = [];
        foreach ($this->lineage($typeName) as $owner) {
            foreach ($this->declaredMembers($owner) as $kind => $named) {
                $members[$kind] = ($members[$kind] ?? []) + $named;
            }
        }

        return $members;
    }

    /**
     * @return array<string, array<string, MemberDefinition>> Only the members the type itself declares, keyed by kind, then name.
     */
    public function declaredMembers(string $typeName): array {
        return array_map(
            static fn(array $named) => array_map(static fn(Declaration $declaration) => $declaration->definition, $named),
            $this->members[$typeName] ?? [],
        );
    }

    /**
     * Returns the whole catalog as an array. Each member is listed once, under the type that declares it.
     * ancestors lists, for each object type, its ancestors' type names in inheritance order.
     * @return array{types: array<string, array<string, array<string, MemberDefinition>>>, ancestors: array<string, list<string>>, functions: array<string, MemberDefinition>}
     */
    public function schema(): array {
        $types = [];
        foreach (array_keys($this->members) as $typeName) {
            $types[$typeName] = $this->declaredMembers($typeName);
        }

        $ancestors = [];
        foreach ($this->objectTypes->names() as $typeName) {
            $lineage = array_slice($this->lineage($typeName), 1);
            if ($lineage !== []) {
                $ancestors[$typeName] = $lineage;
            }
        }

        return ["types" => $types, "ancestors" => $ancestors, "functions" => $this->functions()];
    }

    /**
     * Returns the referenced member as held by the type that declares it.
     */
    private function findInLineage(MemberReference $reference): ?Declaration {
        if ($reference->typeName === null) {
            return $this->functions[$reference->name] ?? null;
        }

        foreach ($this->lineage($reference->typeName) as $owner) {
            $declaration = $this->members[$owner][$reference->kind->value][$reference->name] ?? null;
            if ($declaration !== null) {
                return $declaration;
            }
        }

        return null;
    }

    /**
     * Returns the type and its ancestors in member lookup order.
     * @return non-empty-list<string>
     */
    private function lineage(string $typeName): array {
        return [$typeName, ...array_map(static fn(NamedType $ancestor) => $ancestor->name, $this->objectTypes->ancestors($typeName))];
    }

    /**
     * @param list<string> $typeNames
     */
    private static function validate(Declaration $declaration, array $typeNames): void {
        $reference = $declaration->reference;
        if ($reference->typeName !== null && !in_array($reference->typeName, $typeNames, true)) {
            throw new DefinitionError("Unknown type for {$reference->kind->value} $reference");
        }
    }
}
