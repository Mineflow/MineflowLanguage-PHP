<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

use JsonSerializable;
use Mineflow\Language\Error\DefinitionError;
use Stringable;

/**
 * @phpstan-sealed NamedType|CollectionType|UnionType|PlaceholderType|MixedType|NeverType
 */
abstract readonly class Type implements Stringable, JsonSerializable {
    public static function string(): NamedType {
        return new NamedType(Builtin::String->value);
    }

    public static function number(): NamedType {
        return new NamedType(Builtin::Number->value);
    }

    public static function boolean(): NamedType {
        return new NamedType(Builtin::Boolean->value);
    }

    public static function null(): NamedType {
        return new NamedType(Builtin::Null->value);
    }

    public static function mixed(): MixedType {
        return new MixedType();
    }

    public static function never(): NeverType {
        return new NeverType();
    }

    public static function list(self $element): ListType {
        return new ListType($element);
    }

    public static function map(self $element, ?self $key = null): MapType {
        return new MapType($key ?? MapType::defaultKey(), $element);
    }

    public static function placeholder(Placeholder $placeholder): PlaceholderType {
        return new PlaceholderType($placeholder);
    }

    public static function anyCollection(): self {
        return self::union(self::list(self::mixed()), self::map(self::mixed()));
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

    /**
     * @param class-string $class
     */
    public static function object(string $name, string $class): NamedType {
        if (preg_match("/^[A-Za-z_][A-Za-z0-9_]*$/D", $name) !== 1) {
            throw new DefinitionError("A type name is a word of letters, digits and _, not starting with a digit: \"$name\"");
        }

        $builtin = Builtin::tryFrom($name);
        if ($builtin !== null) {
            throw new DefinitionError("$name is a built-in type. Use #[ExtendType(Builtin::{$builtin->name})] to add members to it.");
        }

        if (Special::tryFrom($name) !== null) {
            throw new DefinitionError("Reserved type name: \"$name\"");
        }

        if (!class_exists($class) && !interface_exists($class)) {
            throw new DefinitionError("Unknown class for the type $name: $class");
        }

        return new NamedType($name, $class);
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
