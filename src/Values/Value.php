<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Types\DeclaredType;

abstract readonly class Value {
    public function type(): DeclaredType {
        return TypeDeclarations::of($this::class);
    }

    /**
     * The PHP value this value stands for: the value itself, unless its class gives another.
     */
    public function native(): mixed {
        return $this;
    }
}
