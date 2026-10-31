<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax;

enum TokenKind: string {
    case Word = "word";
    case Text = "text";
    case Eof = "eof";
    case Operator = "operator";
    case OpenParen = "(";
    case CloseParen = ")";
    case Dot = ".";
    case Comma = ",";
    case OpenBrace = "{";
    case CloseBrace = "}";

    /**
     * Returns the kind of the character if it is a delimiter, or null if it is a word character.
     */
    public static function delimiter(string $char): ?self {
        return match ($char) {
            "(" => self::OpenParen,
            ")" => self::CloseParen,
            "." => self::Dot,
            "," => self::Comma,
            "{" => self::OpenBrace,
            "}" => self::CloseBrace,
            default => BinaryOperator::tryFrom($char) !== null ? self::Operator : null,
        };
    }
}
