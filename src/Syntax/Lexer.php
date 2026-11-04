<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax;

final class Lexer {
    /**
     * @return list<Token>
     */
    public function tokenize(string $source, bool $isTemplate): array {
        /** @var list<Token> $tokens */
        $tokens = [];
        $buffer = "";
        $start = 0;
        $braceDepth = 0;
        $parenDepth = 0;
        $length = strlen($source);

        $flush = static function (int $end, bool $literal) use (&$tokens, &$buffer, &$start): void {
            $text = $literal ? $buffer : trim($buffer);
            if ($text !== "") {
                // A word spans only its text, so the space around it stays between the tokens.
                $leading = strspn($buffer, " \t\n\r\0\x0B", 0, strlen($buffer) - strlen($text));
                $trailing = strlen($buffer) - $leading - strlen($text);
                // phpstan cannot infer the branch where true is passed to $literal in templates.
                // @phpstan-ignore ternary.alwaysFalse
                $tokens[] = new Token($literal ? TokenKind::Text : TokenKind::Word, $text, $start + $leading, $end - $trailing);
            }
            $buffer = "";
        };

        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];
            if ($char === "\\") {
                if ($i + 1 < $length) {
                    $buffer .= $source[++$i];
                }
                continue;
            }
            if ($isTemplate && $braceDepth === 0 && $char !== "{") {
                $buffer .= $char;
                continue;
            }

            // A decimal (`.5`) is read as part of a number. A '.' right after a name, `)`, or `}` is member access, so the digits that follow stay an index.
            $previous = $tokens === [] ? null : $tokens[array_key_last($tokens)]->kind;
            if ($char === "." && !in_array($previous, [TokenKind::Dot, TokenKind::Word, TokenKind::CloseParen, TokenKind::CloseBrace], true)
                && preg_match("/^[0-9]*$/D", ltrim($buffer))
                && $i + 1 < $length && $source[$i + 1] >= "0" && $source[$i + 1] <= "9") {
                $buffer .= $char;
                continue;
            }

            // Square brackets (`value[0]`) are the legacy syntax, read as the same member access as `value.0`.
            if ($char === "[" && preg_match($isTemplate ? "/\\G\\[(\\d+)\\]/" : "/\\G\\[([^\\]]*)\\]/", $source, $match, 0, $i)) {
                $flush($i, false);
                $tokens[] = new Token(TokenKind::Dot, ".", $i, $i + 1);
                foreach ($this->tokenizeFragment($match[1], $i + 1) as $token) {
                    $tokens[] = $token;
                }
                $i += strlen($match[0]) - 1;
                $start = $i + 1;
                continue;
            }

            $kind = TokenKind::delimiter($char);
            if ($kind === null || ($kind === TokenKind::Comma && $parenDepth === 0)) {
                $buffer .= $char;
                continue;
            }

            $flush($i, $isTemplate && $braceDepth === 0);
            $tokens[] = new Token($kind, $char, $i, $i + 1);

            match ($kind) {
                TokenKind::OpenBrace => $braceDepth++,
                TokenKind::CloseBrace => $braceDepth--,
                TokenKind::OpenParen => $parenDepth++,
                TokenKind::CloseParen => $parenDepth--,
                default => null,
            };

            $start = $i + 1;
        }

        $flush($length, $isTemplate && $braceDepth === 0);
        $tokens[] = new Token(TokenKind::Eof, "", $length, $length);

        return $tokens;
    }

    /**
     * Shifts the positions of a separately tokenized fragment by $offset, and removes the fragment's eof.
     *
     * @return list<Token>
     */
    private function tokenizeFragment(string $source, int $offset): array {
        /** @var list<Token> $tokens */
        $tokens = [];
        foreach ($this->tokenize($source, false) as $token) {
            if ($token->kind !== TokenKind::Eof) {
                $tokens[] = new Token($token->kind, $token->text, $token->start + $offset, $token->end + $offset);
            }
        }
        return $tokens;
    }
}
