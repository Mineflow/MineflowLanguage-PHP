<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

final readonly class MixedType extends Type {
    protected function __construct() {
    }

    public function equals(Type $other): bool {
        return $other instanceof self;
    }

    protected function acceptsSingle(Type $actual): bool {
        return true;
    }

    public function resolvePlaceholders(?Type $targetType): Type {
        return $this;
    }

    public function __toString(): string {
        return Special::Mixed->value;
    }
}
