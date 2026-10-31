<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

final readonly class PlaceholderType extends Type {
    protected function __construct(public Placeholder $placeholder) {
    }

    public function equals(Type $other): bool {
        return $other instanceof self && $this->placeholder === $other->placeholder;
    }

    protected function acceptsSingle(Type $actual): bool {
        return $this->equals($actual);
    }

    /**
     * Returns the type this placeholder stands for, given the target type. mixed if the target type is unknown.
     */
    public function resolvePlaceholders(?Type $targetType): Type {
        return match (true) {
            $targetType === null => Type::mixed(),
            $this->placeholder === Placeholder::Target => $targetType,
            !$targetType instanceof CollectionType => Type::mixed(),
            $this->placeholder === Placeholder::Element => $targetType->element,
            default => $targetType->key,
        };
    }

    public function __toString(): string {
        return "\$".$this->placeholder->name;
    }
}
