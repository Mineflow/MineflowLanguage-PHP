<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

final class TypeExpressions {
    /**
     * Resolves a type expression, or the type a class declares.
     * @param TypeExpression|class-string $expression
     */
    public static function resolve(TypeExpression|string $expression, TypeResolver $resolver): Type {
        return is_string($expression) ? $resolver->declared($expression) : $expression->resolve($resolver);
    }
}
