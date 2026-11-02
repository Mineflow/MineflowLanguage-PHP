<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

/**
 * The type a Value class or an interface declares with #[DeclareType]. TypeDeclarations gives the type of a class, and
 * a value gives its own with type(). A value of a class accepted by this type's class is accepted by the type.
 * @phpstan-sealed PlainType|CollectionType
 */
abstract readonly class DeclaredType extends Type {
    /**
     * @param string $name The name the type has in expressions and member declarations.
     * @param class-string $class The Value class or interface that declares the type.
     */
    protected function __construct(public string $name, public string $class) {
    }

    public function equals(Type $other): bool {
        return $other instanceof self && $this->class === $other->class;
    }

    protected function acceptsSingle(Type $actual): bool {
        return $actual instanceof self && ($actual->class === $this->class || is_subclass_of($actual->class, $this->class));
    }

    public function __toString(): string {
        return $this->name;
    }
}
