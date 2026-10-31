<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

final readonly class MapType extends CollectionType {
    protected function __construct(Type $key, Type $element) {
        parent::__construct(Builtin::Map->value, $key, $element);
    }

    /**
     * The key type of a map when no key type is given.
     */
    public static function defaultKey(): Type {
        return Type::union(Type::string(), Type::number());
    }

    public function equals(Type $other): bool {
        return $other instanceof self && $this->key->equals($other->key) && $this->element->equals($other->element);
    }

    protected function acceptsSingle(Type $actual): bool {
        return $actual instanceof self && $this->key->accepts($actual->key) && $this->element->accepts($actual->element);
    }

    public function resolvePlaceholders(?Type $targetType): Type {
        return Type::map($this->element->resolvePlaceholders($targetType), $this->key->resolvePlaceholders($targetType));
    }

    public function __toString(): string {
        $isDefaultShape = $this->element instanceof MixedType && $this->key->equals(self::defaultKey());
        return $isDefaultShape ? $this->name : "$this->name<$this->key,$this->element>";
    }
}
