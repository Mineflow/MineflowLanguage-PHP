<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax;

use Mineflow\Language\Error\UnexpectedToken;
use Mineflow\Language\Syntax\Node\BinaryExpressionNode;
use Mineflow\Language\Syntax\Node\ComputedIdentifierNode;
use Mineflow\Language\Syntax\Node\ComputedNameNode;
use Mineflow\Language\Syntax\Node\ConcatenationNode;
use Mineflow\Language\Syntax\Node\FunctionCallNode;
use Mineflow\Language\Syntax\Node\IdentifierNode;
use Mineflow\Language\Syntax\Node\MethodCallNode;
use Mineflow\Language\Syntax\Node\NameNode;
use Mineflow\Language\Syntax\Node\Node;
use Mineflow\Language\Syntax\Node\NumberNode;
use Mineflow\Language\Syntax\Node\ParenthesizedNode;
use Mineflow\Language\Syntax\Node\PropertyNode;
use Mineflow\Language\Syntax\Node\StringNode;
use Mineflow\Language\Syntax\Node\UnaryExpressionNode;

final class Parser {
    /** @var list<Token> */
    private array $tokens = [];
    private int $position = 0;
    private string $source = "";

    public function parseExpression(string $source): Node {
        return $this->parse($source, false);
    }

    public function parseTemplate(string $source): Node {
        if (!str_contains($source, "{") || !str_contains($source, "}")) {
            return new StringNode($source, 0, strlen($source));
        }
        return $this->parse($source, true);
    }

    private function parse(string $source, bool $isTemplate): Node {
        $this->tokens = (new Lexer())->tokenize($source, $isTemplate);
        $this->position = 0;
        $this->source = $source;
        if ($isTemplate) {
            $children = [];
            while (!$this->at(TokenKind::Eof)) {
                if ($this->at(TokenKind::Text)) {
                    $token = $this->expect(TokenKind::Text);
                    $children[] = new StringNode($token->text, $token->start, $token->end);
                } else {
                    $this->expect(TokenKind::OpenBrace);
                    $children[] = $this->expression();
                    $this->expect(TokenKind::CloseBrace);
                }
            }
            return new ConcatenationNode($children, 0, strlen($source));
        }
        $node = $this->at(TokenKind::Eof) ? new StringNode("", 0, 0) : $this->expression();
        $this->expect(TokenKind::Eof);
        return $node;
    }

    private function expression(int $minimum = 0): Node {
        $left = $this->unary();
        while (true) {
            $operator = $this->operator();
            if ($operator === null || $operator->precedence() < $minimum) {
                return $left;
            }
            $this->position++;
            $right = $this->expression($operator->precedence() + 1);
            $left = new BinaryExpressionNode($left, $operator, $right);
        }
    }

    private function unary(): Node {
        $operator = $this->operator();
        if ($operator !== null && $operator->hasPrefixForm()) {
            $start = $this->tokens[$this->position++]->start;
            return new UnaryExpressionNode($operator, $this->access(), $start);
        }
        return $this->access();
    }

    private function access(): Node {
        $left = $this->atom(false);
        while ($this->at(TokenKind::Dot) || $this->at(TokenKind::OpenParen)) {
            if ($this->at(TokenKind::Dot)) {
                $this->position++;
                $name = $this->atom(true);
                if ($this->at(TokenKind::OpenParen)) {
                    [$arguments, $end] = $this->arguments();
                    $left = new MethodCallNode($left, $name, $arguments, $end);
                } else {
                    $left = new PropertyNode($left, $name);
                }
            } else {
                [$arguments, $end] = $this->arguments();
                $name = match (true) {
                    $left instanceof IdentifierNode => new NameNode($left->name, $left->start, $left->end),
                    $left instanceof ComputedIdentifierNode => new ComputedNameNode($left->name),
                    default => $left,
                };
                $left = new FunctionCallNode($name, $arguments, $end);
            }
        }
        return $left;
    }

    private function atom(bool $asName): Node {
        if ($this->at(TokenKind::OpenParen)) {
            $start = $this->expect(TokenKind::OpenParen)->start;
            $inner = $this->expression();
            $end = $this->expect(TokenKind::CloseParen)->end;
            return new ParenthesizedNode($inner, $start, $end);
        }
        $start = $this->peek()->start;
        $parts = [];
        if ($this->at(TokenKind::Word)) {
            $word = $this->expect(TokenKind::Word);
            $parts[] = new StringNode($word->text, $word->start, $word->end);
        }
        $end = $start;
        $interpolated = false;
        while ($this->at(TokenKind::OpenBrace)) {
            $interpolated = true;
            $this->position++;
            $parts[] = $this->expression();
            $end = $this->expect(TokenKind::CloseBrace)->end;
            if ($this->at(TokenKind::Word)) {
                $word = $this->expect(TokenKind::Word);
                $parts[] = new StringNode($word->text, $word->start, $word->end);
            }
        }
        if ($parts === []) {
            $this->fail("Expected an identifier or parenthesized expression.", TokenKind::Word);
        }
        $end = max($end, $parts[array_key_last($parts)]->end);
        if (!$interpolated) {
            $word = $parts[0]->text;
            $number = $asName ? null : NumberNode::parse($word);
            return match (true) {
                $number !== null => new NumberNode($number, $start, $end),
                $asName => new NameNode($word, $start, $end),
                default => new IdentifierNode($word, $start, $end),
            };
        }
        $concatenated = new ConcatenationNode($parts, $start, $end);
        return $asName ? new ComputedNameNode($concatenated) : new ComputedIdentifierNode($concatenated);
    }

    /**
     * @return array{list<Node>, int}
     */
    private function arguments(): array {
        $this->expect(TokenKind::OpenParen);
        $arguments = [];
        if (!$this->at(TokenKind::CloseParen)) {
            $arguments[] = $this->argument();
            while ($this->at(TokenKind::Comma)) {
                $this->position++;
                $arguments[] = $this->argument();
            }
        }
        return [$arguments, $this->expect(TokenKind::CloseParen)->end];
    }

    private function argument(): Node {
        return $this->isTextArgument() ? $this->textArgument() : $this->expression();
    }

    /**
     * Whether the argument ahead is written text: one with no operator and no parenthesis outside its `{...}`.
     */
    private function isTextArgument(): bool {
        $depth = 0;
        for ($i = $this->position; ; $i++) {
            $kind = $this->tokens[$i]->kind;
            if ($kind === TokenKind::Eof) {
                return true;
            }
            if ($kind === TokenKind::OpenBrace) {
                $depth++;
            } elseif ($kind === TokenKind::CloseBrace) {
                $depth--;
            } elseif ($depth === 0 && in_array($kind, [TokenKind::Comma, TokenKind::CloseParen], true)) {
                return true;
            } elseif ($depth === 0 && in_array($kind, [TokenKind::Operator, TokenKind::OpenParen], true)) {
                return false;
            }
        }
    }

    /**
     * Reads a text argument as it is written, words, dots and the space between them, with each `{...}` in it written as
     * its value's text. One that is only `{expression}` passes the value itself, and one that reads as a number is that number.
     */
    private function textArgument(): Node {
        $start = $this->peek()->start;
        /** @var list<Node> $parts */
        $parts = [];
        $text = "";
        $textStart = $start;
        $previous = null;
        while (!$this->at(TokenKind::Comma) && !$this->at(TokenKind::CloseParen) && !$this->at(TokenKind::CloseBrace) && !$this->at(TokenKind::Eof)) {
            $token = $this->peek();
            if ($previous !== null) {
                // Between two tokens is the space written there, and the `]` that closes a bracket index.
                $text .= preg_replace("/\\S/", "", substr($this->source, $previous->end, $token->start - $previous->end));
            }
            if ($token->kind === TokenKind::OpenBrace) {
                if ($text !== "") {
                    $parts[] = new StringNode($text, $textStart, $token->start);
                    $text = "";
                }
                $this->position++;
                $parts[] = $this->expression();
                $previous = $this->expect(TokenKind::CloseBrace);
                $textStart = $previous->end;
            } else {
                $text .= $token->text;
                $previous = $this->tokens[$this->position++];
            }
        }
        if ($previous === null) {
            $this->fail("Expected an identifier or parenthesized expression.", TokenKind::Word);
        }
        if ($text !== "") {
            $parts[] = new StringNode($text, $textStart, $previous->end);
        }

        if (count($parts) > 1) {
            return new ConcatenationNode($parts, $start, $previous->end);
        }
        $part = $parts[0];
        if (!$part instanceof StringNode) {
            return $part;
        }
        $number = NumberNode::parse($part->text);
        return $number === null ? $part : new NumberNode($number, $part->start, $part->end);
    }

    private function peek(): Token {
        return $this->tokens[$this->position];
    }

    private function at(TokenKind $kind): bool {
        return $this->peek()->kind === $kind;
    }

    private function operator(): ?BinaryOperator {
        $token = $this->peek();
        return $token->kind === TokenKind::Operator ? BinaryOperator::from($token->text) : null;
    }

    private function expect(TokenKind $kind): Token {
        if (!$this->at($kind)) {
            $this->fail("Expected {$kind->value}, got {$this->describe($this->peek())}.", $kind);
        }
        return $this->tokens[$this->position++];
    }

    private function fail(string $message, TokenKind $expected): never {
        $token = $this->peek();

        throw new UnexpectedToken($message, $expected->value, $this->describe($token), $token->start, $token->end);
    }

    private function describe(Token $token): string {
        return $token->kind === TokenKind::Operator ? $token->text : $token->kind->value;
    }
}
