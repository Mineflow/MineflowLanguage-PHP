<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime\Engine;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Diagnostics\Notice;
use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

#[DeclareType("meters")]
final readonly class MetersValue extends Value {
    public function __construct(public int|float $value) {
    }

    #[Operator(BinaryOperator::Multiply)]
    public function scale(int|float $by): MetersValue {
        return new MetersValue($this->value * $by);
    }

    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function scaleFrom(int|float $by): MetersValue {
        return new MetersValue($by * $this->value);
    }

    #[Operator(BinaryOperator::Subtract, reflected: true)]
    public function takenFrom(int|float $from): MetersValue {
        return new MetersValue($from - $this->value);
    }

    #[Operator(BinaryOperator::Add, reflected: true)]
    public function addedTo(MetersValue $other): MetersValue {
        return new MetersValue($other->value + $this->value);
    }

    public function __toString(): string {
        return "meters";
    }
}

#[ExtendType(NumberValue::class)]
final class NumberTimesMeters {
    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function times(int|float $number, MetersValue $meters): MetersValue {
        return new MetersValue(-1);
    }
}

final class OperatorsTest extends TestCase {
    public function testAReflectedOperatorTakesTheLeftOperand(): void {
        $language = $this->meters();
        $result = $language->evaluate("2 * m", $language->scope(["m" => new MetersValue(3)]));

        self::assertInstanceOf(MetersValue::class, $result);
        self::assertEquals(6, $result->value);
    }

    public function testTheLeftOperandsOperatorComesFirst(): void {
        $language = $this->meters();
        $result = $language->evaluate("m * 2", $language->scope(["m" => new MetersValue(3)]));

        self::assertInstanceOf(MetersValue::class, $result);
        self::assertEquals(6, $result->value);
    }

    public function testAReflectedOperatorIsNotUsedBetweenOperandsOfOneType(): void {
        $language = $this->meters();
        $notices = new Notices();

        // So both are calculated as numbers, which meters are not.
        self::assertSame(0, $language->evaluate("m + m", $language->scope(["m" => new MetersValue(3)]), $notices)->native());
        self::assertSame(array_fill(0, 2, "A value of the type meters is not a number, so 0 is used."), $this->messages($notices));
    }

    public function testAReflectedOperatorHasToTakeTheLeftOperand(): void {
        $language = $this->meters();
        $notices = new Notices();

        // So the operator of the left operand is used, with the right one converted to the number it takes.
        self::assertSame("", $language->evaluate("s * m", $language->scope(["s" => "ab", "m" => new MetersValue(3)]), $notices)->native());
        self::assertSame(["A value of the type meters is not a number, so 0 is used."], $this->messages($notices));
    }

    public function testAReflectedOperatorGivesAPrefixForm(): void {
        $language = $this->meters();
        $scope = $language->scope(["m" => new MetersValue(3)]);
        $result = $language->evaluate("-m", $scope);
        self::assertInstanceOf(MetersValue::class, $result);
        self::assertEquals(-3, $result->value);

        self::assertSame(0, $language->evaluate("+m", $scope)->native());
    }

    public function testTextThatReadsAsANumberIsThatNumberNextToANumber(): void {
        $language = Language::create();
        $scope = $language->scope(["three" => "3", "four" => "4", "word" => "ab"]);

        self::assertSame(7, $language->evaluate("three + four", $scope)->native());
        self::assertSame(5.0, $language->evaluate("2 + three", $scope)->native());
        self::assertSame(6.0, $language->evaluate("three * 2", $scope)->native());
        self::assertSame(-1, $language->evaluate("three - four", $scope)->native());
        self::assertSame("3ab", $language->evaluate("three + word", $scope)->native());
    }

    public function testPlusWithTextOnEitherSideJoinsTheText(): void {
        $language = Language::create();
        $scope = $language->scope(["word" => "ab", "nothing" => null, "yes" => true]);

        self::assertSame("2ab", $language->evaluate("2 + word", $scope)->native());
        self::assertSame("nullab", $language->evaluate("nothing + word", $scope)->native());
        self::assertSame("trueab", $language->evaluate("yes + word", $scope)->native());
    }

    public function testAnOperandIsConvertedToWhatTheOperatorTakes(): void {
        $language = Language::create();
        $scope = $language->scope(["word" => "ab", "nothing" => null, "yes" => true]);
        $notices = new Notices();

        self::assertSame(2.0, $language->evaluate("2 + nothing", $scope, $notices)->native());
        self::assertSame(3.0, $language->evaluate("2 + yes", $scope, $notices)->native());
        self::assertSame(2.0, $language->evaluate("nothing + 2", $scope, $notices)->native());
        self::assertSame([], $this->messages($notices));

        self::assertSame(2.0, $language->evaluate("2 - word", $scope, $notices)->native());
        self::assertSame(["\"ab\" is not a number, so 0 is used."], $this->messages($notices));
    }

    /**
     * @return list<string>
     */
    private function messages(Notices $notices): array {
        return array_map(static fn(Notice $notice) => $notice->message, $notices->all());
    }

    private function meters(): Language {
        return (new LanguageBuilder())->addType(MetersValue::class)->addExtension(new NumberTimesMeters())->build();
    }
}
