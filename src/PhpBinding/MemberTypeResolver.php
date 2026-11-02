<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\DeclaredType;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeRegistry;
use Mineflow\Language\Types\TypeResolver;

final readonly class MemberTypeResolver implements TypeResolver {
    public function __construct(private TypeRegistry $types, private ?Type $targetType) {
    }

    public function declared(string $class): DeclaredType {
        return $this->types->of($class);
    }

    public function placeholder(Placeholder $placeholder): Type {
        $allowed = $placeholder === Placeholder::Target ? $this->targetType !== null : $this->targetType instanceof CollectionType;
        if (!$allowed) {
            throw new DefinitionError("Placeholder::{$placeholder->name} is only available in member declarations of a collection.");
        }

        return Type::placeholder($placeholder);
    }
}
