<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Syntax;

use Mineflow\Language\Syntax\Lexer;
use Mineflow\Language\Syntax\Token;
use Mineflow\Language\Syntax\TokenKind;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase {
    /**
     * @return list<string>
     */
    private function kinds(string $source, bool $isTemplate = false): array {
        return array_map(static fn(Token $token) => $token->kind->value, (new Lexer())->tokenize($source, $isTemplate));
    }

    /**
     * @return list<string>
     */
    private function words(string $source, bool $isTemplate = false): array {
        $tokens = array_filter(
            (new Lexer())->tokenize($source, $isTemplate),
            static fn(Token $token) => in_array($token->kind, [TokenKind::Word, TokenKind::Text], true),
        );

        return array_values(array_map(static fn(Token $token) => $token->text, $tokens));
    }

    public function testSurroundingSpaceIsNotPartOfAWord(): void {
        self::assertSame(["padded"], $this->words("  padded  "));
    }

    public function testACommaSeparatesOnlyInsideAnArgumentList(): void {
        self::assertSame(["a,b"], $this->words("a,b"));
        self::assertSame(["f", "a", "b"], $this->words("f(a,b)"));
    }

    public function testADecimalPointBelongsToTheNumberButAMemberDoesNot(): void {
        self::assertSame(["1.5"], $this->words("1.5"));
        self::assertSame(["word", "eof"], $this->kinds("1.5"));
        self::assertSame(["word", ".", "word", "eof"], $this->kinds("a.5"));
    }

    public function testBracketNotationBecomesMemberAccessAtTheOffsetItWasWrittenAt(): void {
        $tokens = (new Lexer())->tokenize("scores[0]", false);

        self::assertSame(["word", ".", "word", "eof"], $this->kinds("scores[0]"));
        self::assertSame(".", $tokens[1]->text);
        self::assertSame([6, 7], [$tokens[1]->start, $tokens[1]->end]);
        // The contents of square brackets are tokenized separately, with positions matching where they were written in the original source.
        self::assertSame("0", $tokens[2]->text);
        self::assertSame([7, 8], [$tokens[2]->start, $tokens[2]->end]);
    }

    public function testATemplateIsLiteralTextUntilABraceOpens(): void {
        self::assertSame(["text", "{", "word", "}", "text", "eof"], $this->kinds("hi {name}!", true));
        self::assertSame(["hi ", "name", "!"], $this->words("hi {name}!", true));
    }

    public function testABackslashKeepsTheNextCharacterOutOfTheSyntax(): void {
        self::assertSame(["text", "eof"], $this->kinds('\{name} x', true));
        self::assertSame(["{name} x"], $this->words('\{name} x', true));
    }
}
