<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

final readonly class ListType extends CollectionType {
    protected function __construct(Type $element) {
        parent::__construct(Builtin::List->value, Type::number(), $element);
    }

    public function equals(Type $other): bool {
        return $other instanceof self && $this->element->equals($other->element);
    }

    protected function acceptsSingle(Type $actual): bool {
        return $actual instanceof self && $this->element->accepts($actual->element);
    }

    public function resolvePlaceholders(?Type $targetType): Type {
        return Type::list($this->element->resolvePlaceholders($targetType));
    }

    public function __toString(): string {
        return $this->element instanceof MixedType ? $this->name : "$this->name<$this->element>";
    }
}
