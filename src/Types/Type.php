<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

use JsonSerializable;
use Stringable;

/**
 * @phpstan-sealed DeclaredType|UnionType|PlaceholderType|MixedType|NeverType
 */
abstract readonly class Type implements Stringable, JsonSerializable {
    public static function mixed(): MixedType {
        return new MixedType();
    }

    public static function never(): NeverType {
        return new NeverType();
    }

    public static function placeholder(Placeholder $placeholder): PlaceholderType {
        return new PlaceholderType($placeholder);
    }

    public static function union(self ...$types): self {
        /** @var list<self> $members */
        $members = [];

        foreach ($types as $type) {
            foreach ($type instanceof UnionType ? $type->members : [$type] as $member) {
                if ($member instanceof MixedType) {
                    return $member;
                }
                if ($member instanceof NeverType || self::contains($members, $member)) {
                    continue;
                }

                $members[] = $member;
            }
        }

        usort($members, static fn(self $a, self $b) => strcmp((string)$a, (string)$b));

        return match (count($members)) {
            0 => self::never(),
            1 => $members[0],
            default => new UnionType($members),
        };
    }

    abstract public function equals(self $other): bool;

    /**
     * Whether a value of the given type can be used where this type is expected.
     */
    final public function accepts(self $actual): bool {
        if ($actual instanceof NeverType) {
            return true;
        }

        if ($actual instanceof UnionType) {
            return array_filter($actual->members, fn(self $member) => !$this->accepts($member)) === [];
        }

        return $this->acceptsSingle($actual);
    }

    /**
     * Whether this type accepts a type that is neither never nor a union.
     */
    abstract protected function acceptsSingle(self $actual): bool;

    abstract public function resolvePlaceholders(?self $targetType): self;

    /**
     * @param list<self> $types
     */
    protected static function contains(array $types, self $type): bool {
        foreach ($types as $candidate) {
            if ($candidate->equals($type)) {
                return true;
            }
        }
        return false;
    }

    final public function jsonSerialize(): string {
        return (string)$this;
    }
}
