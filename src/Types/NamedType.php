<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

final readonly class NamedType extends Type {
    /**
     * @param string $name The type name used when declaring members.
     * @param class-string|null $class The PHP class or interface an object type represents. null for built-in types.
     */
    protected function __construct(public string $name, public ?string $class = null) {
    }

    public function equals(Type $other): bool {
        return $other instanceof self && $this->name === $other->name && $this->class === $other->class;
    }

    /**
     * An object type also accepts its subtypes. A subtype is a type declared for a class that extends or implements this class.
     */
    protected function acceptsSingle(Type $actual): bool {
        return $this->equals($actual)
            || ($this->class !== null && $actual instanceof self && $actual->class !== null && is_subclass_of($actual->class, $this->class));
    }

    public function resolvePlaceholders(?Type $targetType): Type {
        return $this;
    }

    public function __toString(): string {
        return $this->name;
    }
}
