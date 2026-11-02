<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Types\TypeExpressions;
use Mineflow\Language\Types\TypeResolver;

/**
 * The type of CollectionValue class with its key and element types given. One left out is that of any value of the
 * class. In #[DeclareType(typeHint: ...)], one left out stays as the parent class leaves it.
 * @phpstan-sealed CollectionOf|ListOf|MapOf
 */
abstract readonly class CollectionExpression implements TypeExpression {
    /**
     * @param class-string $class
     * @param TypeExpression|class-string|null $element
     * @param TypeExpression|class-string|null $key
     */
    protected function __construct(
        public string                     $class,
        public TypeExpression|string|null $element,
        public TypeExpression|string|null $key,
    ) {
    }

    public function resolve(TypeResolver $resolver): Type {
        $type = $resolver->declared($this->class);
        if (!$type instanceof CollectionType) {
            throw new DefinitionError("$type holds no items, so it takes no key or element type");
        }

        return $type->withArguments(
            $this->key === null ? null : TypeExpressions::resolve($this->key, $resolver),
            $this->element === null ? null : TypeExpressions::resolve($this->element, $resolver),
        );
    }
}
