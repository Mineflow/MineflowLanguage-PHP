<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Types\TypeExpressions;
use Mineflow\Language\Types\TypeResolver;
use Mineflow\Language\Values\NullValue;

final readonly class Nullable implements TypeExpression {
    /**
     * @param TypeExpression|class-string $inner
     */
    public function __construct(public TypeExpression|string $inner) {
    }

    public function resolve(TypeResolver $resolver): Type {
        return Type::union(TypeExpressions::resolve($this->inner, $resolver), $resolver->declared(NullValue::class));
    }
}
