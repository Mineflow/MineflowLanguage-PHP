<?php
declare(strict_types=1);

namespace Mineflow\Language\Definition;

use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Runtime\MemberImplementation;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\Type;

final readonly class Declaration {
    public function __construct(
        public MemberReference      $reference,
        public MemberDefinition     $definition,
        public MemberImplementation $implementation,
    ) {
        self::check($reference, $definition);
    }

    public function withTypeName(string $typeName): self {
        return new self($this->reference->withTypeName($typeName), $this->definition, $this->implementation);
    }

    /**
     * Checks the shape of parameters and return value required by the member's kind.
     */
    private static function check(MemberReference $reference, MemberDefinition $definition): void {
        $kind = $reference->kind;
        if (in_array($kind, [MemberKind::Index, MemberKind::Entries], true) && Builtin::tryFrom((string)$reference->typeName) !== null) {
            throw new DefinitionError("A built-in type cannot declare {$kind->value}: $reference");
        }

        $parameters = $definition->parameters;
        if (!$kind->takesArguments() && $parameters !== []) {
            throw new DefinitionError("No language arguments for {$kind->value}: $reference");
        }

        $single = count($parameters) === 1 && !$parameters[0]->optional && !$parameters[0]->variadic;
        $problem = match ($kind) {
            MemberKind::Index => $single && $parameters[0]->type->accepts(Type::string()) ? null : "An index takes exactly one name, as a string",
            MemberKind::Entries => $definition->returns instanceof CollectionType ? null : "Entries are either a list or a map",
            MemberKind::Operator => $single ? null : "A binary operator requires exactly one right operand",
            MemberKind::ReflectedOperator => $single ? null : "A binary operator requires exactly one left operand",
            MemberKind::Property, MemberKind::Method, MemberKind::Function => null,
        };
        if ($problem !== null) {
            throw new DefinitionError("$problem: $reference");
        }
    }
}
