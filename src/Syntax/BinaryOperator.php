<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax;

enum BinaryOperator: string {
    case Add = "+";
    case Subtract = "-";
    case Multiply = "*";
    case Divide = "/";

    /**
     * Higher values bind first (in `1 + 2 * 3`, the multiplication comes first).
     */
    public function precedence(): int {
        return match ($this) {
            self::Add, self::Subtract => 1,
            self::Multiply, self::Divide => 2,
        };
    }

    /**
     * Whether the operator has a prefix form. The prefix form is evaluated as `0 symbol operand`, so only operators that can take 0 on the left have one.
     */
    public function hasPrefixForm(): bool {
        return match ($this) {
            self::Add, self::Subtract => true,
            self::Multiply, self::Divide => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function symbols(): array {
        return array_map(static fn(self $operator) => $operator->value, self::cases());
    }
}
