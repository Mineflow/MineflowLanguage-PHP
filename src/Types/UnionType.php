<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

final readonly class UnionType extends Type {
    /**
     * @param list<Type> $members
     */
    protected function __construct(public array $members) {
    }

    public function equals(Type $other): bool {
        return $other instanceof self && count($this->members) === count($other->members)
            && array_filter($this->members, static fn(Type $member) => !self::contains($other->members, $member)) === [];
    }

    protected function acceptsSingle(Type $actual): bool {
        return array_filter($this->members, static fn(Type $member) => $member->accepts($actual)) !== [];
    }

    public function resolvePlaceholders(?Type $targetType): Type {
        return Type::union(...array_map(static fn(Type $member) => $member->resolvePlaceholders($targetType), $this->members));
    }

    public function __toString(): string {
        return implode("|", $this->members);
    }
}
