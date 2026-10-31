<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Types\TypeResolver;

final readonly class ListOf implements TypeExpression {
    public function __construct(public TypeExpression $element) {
    }

    public function resolve(TypeResolver $resolver): Type {
        return Type::list($this->element->resolve($resolver));
    }
}
