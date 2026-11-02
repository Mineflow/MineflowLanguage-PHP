<?php
declare(strict_types=1);

namespace Mineflow\Language\Definition;

use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\TypeDeclarations;

final readonly class Declaration {
    public function __construct(
        public MemberReference  $reference,
        public MemberDefinition $definition,
    ) {
        self::check($reference, $definition);
    }

    public function withTypeName(string $typeName): self {
        return new self($this->reference->withTypeName($typeName), $this->definition);
    }

    /**
     * Checks the shape of parameters and return value required by the member's kind.
     */
    private static function check(MemberReference $reference, MemberDefinition $definition): void {
        $kind = $reference->kind;
        $parameters = $definition->parameters;
        if (!$kind->takesArguments() && $parameters !== []) {
            throw new DefinitionError("No language arguments for {$kind->value}: $reference");
        }

        $single = count($parameters) === 1 && !$parameters[0]->optional && !$parameters[0]->variadic;
        $problem = match ($kind) {
            MemberKind::Index => $single && $parameters[0]->type->accepts(TypeDeclarations::of(StringValue::class)) ? null : "An index takes exactly one name, as a string",
            MemberKind::Operator => $single ? null : "A binary operator requires exactly one right operand",
            MemberKind::ReflectedOperator => $single ? null : "A binary operator requires exactly one left operand",
            MemberKind::Property, MemberKind::Method, MemberKind::Function => null,
        };
        if ($problem !== null) {
            throw new DefinitionError("$problem: $reference");
        }
    }
}
