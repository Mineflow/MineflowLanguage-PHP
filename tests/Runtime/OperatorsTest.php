<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Builtin;
use PHPUnit\Framework\TestCase;

final class Meters {
    public function __construct(public int|float $value) {
    }
}

#[DeclareType(Meters::class, name: "meters")]
final class MetersType {
    #[Operator(BinaryOperator::Multiply)]
    public function scale(Meters $meters, int|float $by): Meters {
        return new Meters($meters->value * $by);
    }

    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function scaleFrom(Meters $meters, int|float $by): Meters {
        return new Meters($by * $meters->value);
    }

    #[Operator(BinaryOperator::Subtract, reflected: true)]
    public function takenFrom(Meters $meters, int|float $from): Meters {
        return new Meters($from - $meters->value);
    }

    #[Operator(BinaryOperator::Add, reflected: true)]
    public function addedTo(Meters $meters, Meters $other): Meters {
        return new Meters($other->value + $meters->value);
    }
}

#[ExtendType(Builtin::Number)]
final class NumberTimesMeters {
    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function times(int|float $number, Meters $meters): Meters {
        return new Meters(-1);
    }
}

final class OperatorsTest extends TestCase {
    public function testAReflectedOperatorTakesTheLeftOperand(): void {
        $language = $this->meters();
        $result = $language->evaluate("2 * m", $language->scope(["m" => new Meters(3)]))->native();

        self::assertInstanceOf(Meters::class, $result);
        self::assertEquals(6, $result->value);
    }

    public function testTheLeftOperandsOperatorComesFirst(): void {
        $language = $this->meters();
        $result = $language->evaluate("m * 2", $language->scope(["m" => new Meters(3)]))->native();

        self::assertInstanceOf(Meters::class, $result);
        self::assertEquals(6, $result->value);
    }

    public function testAReflectedOperatorIsNotUsedBetweenOperandsOfOneType(): void {
        $language = $this->meters();

        $this->expectException(UnsupportedCalculation::class);
        $this->expectExceptionMessage("Unsupported calculation: meters + meters");

        $language->evaluate("m + m", $language->scope(["m" => new Meters(3)]));
    }

    public function testAReflectedOperatorHasToTakeTheLeftOperand(): void {
        $language = $this->meters();

        $this->expectException(UnsupportedCalculation::class);
        $this->expectExceptionMessage("Unsupported calculation: string * meters");

        $language->evaluate("s * m", $language->scope(["s" => "ab", "m" => new Meters(3)]));
    }

    public function testAReflectedOperatorGivesAPrefixForm(): void {
        $language = $this->meters();
        $scope = $language->scope(["m" => new Meters(3)]);
        $result = $language->evaluate("-m", $scope)->native();
        self::assertInstanceOf(Meters::class, $result);
        self::assertEquals(-3, $result->value);

        $this->expectException(UnsupportedCalculation::class);
        $this->expectExceptionMessage("Unsupported calculation: number + meters");
        $language->evaluate("+m", $scope);
    }

    private function meters(): Language {
        return (new LanguageBuilder())->addType(new MetersType())->addExtension(new NumberTimesMeters())->build();
    }
}
