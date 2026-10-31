<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Syntax;

use Mineflow\Language\Error\UnexpectedToken;
use Mineflow\Language\Syntax\Node\StringNode;
use Mineflow\Language\Syntax\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase {
    private Parser $parser;

    protected function setUp(): void {
        $this->parser = new Parser();
    }

    public function testAnEmptyExpressionIsTheEmptyString(): void {
        $node = $this->parser->parseExpression("");

        self::assertInstanceOf(StringNode::class, $node);
        self::assertSame("", $node->text);
    }

    public function testAnUnexpectedTokenReportsWhatWasWantedAndWhereItStopped(): void {
        try {
            $this->parser->parseExpression("a.b(1");

            self::fail("Expected a syntax error.");
        } catch (UnexpectedToken $error) {
            self::assertSame([")", "eof"], [$error->expected, $error->actual]);
            self::assertSame([5, 5], [$error->start(), $error->end()]);
        }
    }

    public function testAnOperatorWithoutARightOperandIsASyntaxError(): void {
        $this->expectException(UnexpectedToken::class);
        $this->expectExceptionMessage("Expected an identifier or parenthesized expression.");

        $this->parser->parseExpression("1 +");
    }

    public function testOnlyAnOperatorWithAPrefixFormMayPrecedeItsOperand(): void {
        $this->expectException(UnexpectedToken::class);
        
        $this->parser->parseExpression("*5");
    }
}
