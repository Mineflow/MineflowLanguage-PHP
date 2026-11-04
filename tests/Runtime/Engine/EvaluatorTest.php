<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime\Engine;

use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Error\ArgumentTypeMismatch;
use Mineflow\Language\Error\SourceError;
use Mineflow\Language\Error\UndefinedVariable;
use Mineflow\Language\Error\UnexpectedToken;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Runtime\Scope;
use PHPUnit\Framework\TestCase;

final class Rules {
    #[Method]
    public function identity(string $value): string {
        return $value;
    }

    #[Method]
    public function double(int $number): int {
        return $number * 2;
    }
}

/**
 * How source code is read: what a word, braces and an operator mean when evaluated.
 */
final class EvaluatorTest extends TestCase {
    private Language $language;
    private Scope $scope;

    protected function setUp(): void {
        $this->language = (new LanguageBuilder())->addFunctions(new Rules())->build();
        $this->scope = $this->language->scope([
            "name" => "aieuo",
            "which" => "name",
            "prefix" => "na",
            "n" => "5",
            "a" => "x",
            "b" => "y",
            "scores" => [3, 1, 2],
            "user" => ["name" => "aieuo", "age" => 20],
            "key" => "name",
            "fn" => "identity",
        ]);
    }

    public function testAWordThatReadsAsANumberIsThatNumber(): void {
        self::assertSame(2.0, $this->evaluate("2"));
        self::assertSame(0.5, $this->evaluate(".5"));
        self::assertSame(1000.0, $this->evaluate("1e3"));
        // Whether a word is a number is decided by PHP's is_numeric(), so a hexadecimal word is a variable name.
        self::assertSame(UndefinedVariable::class, $this->errorOf("0x1A"));
    }

    public function testEveryNumberIsAFloatAndIsWrittenWithoutAnEmptyFraction(): void {
        self::assertSame(3.0, $this->evaluate("1 + 2"));
        self::assertSame(3.5, $this->evaluate("7 / 2"));
        self::assertSame("3", $this->language->render("{1 + 2}"));
    }

    public function testMultiplicationBindsTighterThanAdditionUnlessParenthesized(): void {
        self::assertSame(7.0, $this->evaluate("1 + 2 * 3"));
        self::assertSame(1.0, $this->evaluate("8 / 4 - 1"));
        self::assertSame(9.0, $this->evaluate("(1 + 2) * 3"));
    }

    public function testAPrefixOperatorIsEvaluatedWithZeroOnItsLeft(): void {
        self::assertSame(-5.0, $this->evaluate("-5"));
        self::assertSame(5.0, $this->evaluate("+5"));
        // So using the prefix form requires the type to have a - that takes a number as its left operand. string has no such operator.
        self::assertSame(UnsupportedCalculation::class, $this->errorOf("-name"));
    }

    public function testABareArgumentIsTextAndBracesPassTheValue(): void {
        self::assertSame("bar", $this->evaluate("identity(bar)"));
        self::assertSame("aieuo", $this->evaluate("identity({name})"));
        self::assertSame("xy", $this->evaluate("identity({a}{b})"));
        // A number is a literal, so it is passed as a number and does not match a string parameter.
        self::assertSame(ArgumentTypeMismatch::class, $this->errorOf("identity(1)"));
    }

    public function testAnArgumentWithoutAnOperatorOrAParenthesisIsTheTextWrittenThere(): void {
        // Dots are text as well, even after the name of a variable.
        self::assertSame("user.name", $this->evaluate("identity(user.name)"));
        self::assertSame("Hello world.", $this->evaluate("identity(Hello world.)"));
        self::assertSame("two  words .", $this->evaluate("identity( two  words . )"));
        self::assertSame(".", $this->evaluate("identity(.)"));
        // A bracket index reads as the dot it stands for.
        self::assertSame("scores.0", $this->evaluate("identity(scores[0])"));
        self::assertSame(4, $this->evaluate("double(2.)"));
    }

    public function testBracesInATextArgumentWriteTheValueAsText(): void {
        self::assertSame("x.name", $this->evaluate("identity({a}.name)"));
        self::assertSame("<x y>", $this->evaluate("identity(<{a} {b}>)"));
    }

    public function testAnArgumentWithAnOperatorOrAParenthesisIsAnExpression(): void {
        self::assertSame("aieuo", $this->evaluate("identity((user.name))"));
        self::assertSame("AIEUO", $this->evaluate("identity(user.name.uppercase())"));
        self::assertSame(8, $this->evaluate("double({n} - 1)"));
    }

    public function testAnEmptyArgumentIsASyntaxError(): void {
        self::assertSame(UnexpectedToken::class, $this->errorOf("identity(a,)"));
    }

    public function testAMethodTakesTheTextOfANumberWhereAnOperatorDoesNot(): void {
        self::assertSame(10, $this->evaluate("double({n})"));
        self::assertSame(UnsupportedCalculation::class, $this->errorOf("1 + n"));
    }

    public function testBracesInAnExpressionNameTheVariableToRead(): void {
        self::assertSame("aieuo", $this->evaluate("{which}"));
        self::assertSame("aieuo", $this->evaluate("{prefix}me"));
        self::assertSame("aieuo", $this->evaluate("user.{key}"));
        // In a template the same braces are an interpolation, so the name itself is written out.
        self::assertSame("name", $this->language->render("{which}", $this->scope));
    }

    public function testAnItemIsReadByItsIndexAndAnEntryByItsKey(): void {
        self::assertSame(3, $this->evaluate("scores.0"));
        self::assertSame(3, $this->evaluate("scores[0]"));
        self::assertSame("aieuo", $this->evaluate("user.name"));
        self::assertSame("aieuo", $this->evaluate("user[name]"));
    }

    public function testTheTextInFrontOfAnOpeningParenthesisNamesTheFunction(): void {
        self::assertSame("hi", $this->evaluate("{fn}(hi)"));
        self::assertSame("hi", $this->evaluate("(fn)(hi)"));
    }

    private function evaluate(string $source): mixed {
        return $this->language->evaluate($source, $this->scope)->native();
    }

    private function errorOf(string $source): string {
        try {
            $this->language->evaluate($source, $this->scope);
        } catch (SourceError $error) {
            return $error::class;
        }

        self::fail("Evaluating `$source` should have failed.");
    }
}
