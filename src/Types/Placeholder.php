<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

/**
 * A type that is decided once the target value is known. In declarations, it refers to the target value's type and its element type.
 */
enum Placeholder implements TypeExpression {
    case Target;
    case Element;
    case Key;

    public function resolve(TypeResolver $resolver): Type {
        return $resolver->placeholder($this);
    }
}
