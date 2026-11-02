<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime\Engine;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Error\UnsupportedCalculation;
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

        $this->expectException(UnsupportedCalculation::class);
        $this->expectExceptionMessage("Unsupported calculation: meters + meters");

        $language->evaluate("m + m", $language->scope(["m" => new MetersValue(3)]));
    }

    public function testAReflectedOperatorHasToTakeTheLeftOperand(): void {
        $language = $this->meters();

        $this->expectException(UnsupportedCalculation::class);
        $this->expectExceptionMessage("Unsupported calculation: string * meters");

        $language->evaluate("s * m", $language->scope(["s" => "ab", "m" => new MetersValue(3)]));
    }

    public function testAReflectedOperatorGivesAPrefixForm(): void {
        $language = $this->meters();
        $scope = $language->scope(["m" => new MetersValue(3)]);
        $result = $language->evaluate("-m", $scope);
        self::assertInstanceOf(MetersValue::class, $result);
        self::assertEquals(-3, $result->value);

        $this->expectException(UnsupportedCalculation::class);
        $this->expectExceptionMessage("Unsupported calculation: number + meters");
        $language->evaluate("+m", $scope);
    }

    private function meters(): Language {
        return (new LanguageBuilder())->addType(MetersValue::class)->addExtension(new NumberTimesMeters())->build();
    }
}
