<?php
declare(strict_types=1);

namespace Mineflow\Language\Definition;

use Mineflow\Language\Syntax\BinaryOperator;
use Stringable;

final readonly class MemberReference implements Stringable {
    public const string ANY = "*";

    private function __construct(public ?string $typeName, public MemberKind $kind, public string $name) {
    }

    public static function property(string $typeName, string $name): self {
        return new self($typeName, MemberKind::Property, $name);
    }

    public static function method(string $typeName, string $name): self {
        return new self($typeName, MemberKind::Method, $name);
    }

    public static function operator(string $typeName, BinaryOperator $operator): self {
        return new self($typeName, MemberKind::Operator, $operator->value);
    }

    public static function reflectedOperator(string $typeName, BinaryOperator $operator): self {
        return new self($typeName, MemberKind::ReflectedOperator, $operator->value);
    }

    public static function index(string $typeName): self {
        return new self($typeName, MemberKind::Index, self::ANY);
    }

    public static function entries(string $typeName): self {
        return new self($typeName, MemberKind::Entries, self::ANY);
    }

    public static function function (string $name): self {
        return new self(null, MemberKind::Function, $name);
    }

    public function withTypeName(string $typeName): self {
        return new self($typeName, $this->kind, $this->name);
    }

    public function __toString(): string {
        return $this->typeName === null ? $this->name : "{$this->typeName}.{$this->name}";
    }
}
