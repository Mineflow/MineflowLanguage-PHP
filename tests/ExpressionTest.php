<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests;

use Mineflow\Language\Error\UnexpectedToken;
use Mineflow\Language\Expression;
use Mineflow\Language\Language;
use Mineflow\Language\Runtime\Scope;
use PHPUnit\Framework\TestCase;

final class ExpressionTest extends TestCase {
    private Language $language;
    private Scope $scope;

    protected function setUp(): void {
        $this->language = Language::create();
        $this->scope = $this->language->scope(["scores" => [3, 1, 2], "n" => 5]);
    }

    public function testAnExpressionEvaluatesToWhatItsSourceEvaluatesToInEveryScope(): void {
        $expression = new Expression("n * 2 + scores.first()");

        self::assertSame(13.0, $this->language->evaluate($expression, $this->scope)->native());
        self::assertSame(3.0, $this->language->evaluate($expression, $this->language->scope(["n" => 1, "scores" => [1]]))->native());
    }

    public function testTheSourceIsParsedOnceAndOnlyWhenAsked(): void {
        $broken = new Expression("1 +");
        self::assertSame("1 +", $broken->source);

        $expression = new Expression("n");
        self::assertSame($expression->node(), $expression->node());
    }

    public function testASyntaxErrorIsKeptAndThrownAgainWithItsPosition(): void {
        $expression = new Expression("a.b(1");

        $error = $expression->syntaxError();
        self::assertInstanceOf(UnexpectedToken::class, $error);
        self::assertSame(5, $error->start());

        foreach ([1, 2] as $_) {
            try {
                $this->language->evaluate($expression, $this->scope);
                self::fail("Expected a syntax error.");
            } catch (UnexpectedToken $thrown) {
                self::assertSame($error, $thrown);
            }
        }
        self::assertNull((new Expression("n"))->syntaxError());
    }
}
