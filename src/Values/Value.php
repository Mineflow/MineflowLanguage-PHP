<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Types\DeclaredType;
use Stringable;

abstract readonly class Value implements Stringable {
    public function type(): DeclaredType {
        return TypeDeclarations::of($this::class);
    }

    /**
     * The PHP value this value stands for: the value itself, unless its class gives another.
     */
    public function native(): mixed {
        return $this;
    }

    abstract public function __toString(): string;
}
