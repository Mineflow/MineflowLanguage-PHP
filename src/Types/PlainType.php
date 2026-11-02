<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

/**
 * The type of a class that holds no items: one that is not a CollectionValue, or an interface.
 * @internal Hosts get it as a DeclaredType.
 */
final readonly class PlainType extends DeclaredType {
    /**
     * @param class-string $class
     */
    public function __construct(string $name, string $class) {
        parent::__construct($name, $class);
    }

    public function resolvePlaceholders(?Type $targetType): Type {
        return $this;
    }
}
