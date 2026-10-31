<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

interface TypeExpression {
    public function resolve(TypeResolver $resolver): Type;
}
