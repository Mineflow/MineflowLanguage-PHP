<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

/**
 * Types that no value has as its own type. Members cannot be declared on them.
 */
enum Special: string implements TypeExpression {
    case Mixed = "mixed";
    /** A type accepted by every type. It is the element type of an empty collection. */
    case Never = "never";

    public function resolve(TypeResolver $resolver): Type {
        return match ($this) {
            self::Mixed => Type::mixed(),
            self::Never => Type::never(),
        };
    }
}
