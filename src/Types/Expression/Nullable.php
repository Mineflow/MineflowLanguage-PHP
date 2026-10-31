<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Types\TypeResolver;

final readonly class Nullable implements TypeExpression {
    public function __construct(public TypeExpression $inner) {
    }

    public function resolve(TypeResolver $resolver): Type {
        return Type::union($this->inner->resolve($resolver), Type::null());
    }
}
