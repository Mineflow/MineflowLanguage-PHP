<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Types\TypeResolver;

final readonly class MapOf implements TypeExpression {
    public function __construct(
        public TypeExpression $element,
        public TypeExpression $key = new OneOf(Builtin::String, Builtin::Number),
    ) {
    }

    public function resolve(TypeResolver $resolver): Type {
        return Type::map($this->element->resolve($resolver), $this->key->resolve($resolver));
    }
}
