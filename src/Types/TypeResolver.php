<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

interface TypeResolver {
    /**
     * The type a Value class or an interface declares.
     * @param class-string $class
     */
    public function declared(string $class): DeclaredType;

    public function placeholder(Placeholder $placeholder): Type;
}
