<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\StandardLibrary;

use Mineflow\Language\Error\DivisionByZero;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Language;
use Mineflow\Language\Syntax\BinaryOperator;
use PHPUnit\Framework\TestCase;

final class NumberMembersTest extends TestCase {
    private Language $language;

    protected function setUp(): void {
        $this->language = Language::create();
    }

    public function testArithmetic(): void {
        self::assertSame(3.0, $this->language->evaluate("1 + 2")->native());
        self::assertSame(-1.0, $this->language->evaluate("1 - 2")->native());
        self::assertSame(6.0, $this->language->evaluate("2 * 3")->native());
        self::assertSame(3.5, $this->language->evaluate("7 / 2")->native());
    }

    public function testDividingByZeroIsReportedAsItsOwnDiagnostic(): void {
        try {
            $this->language->evaluate("1 / 0");

            self::fail("Dividing by zero should not produce a value.");
        } catch (DivisionByZero $error) {
            self::assertSame(BinaryOperator::Divide, $error->operator);
        }
    }

    public function testANumberIsNeverJoinedToAStringOnItsLeft(): void {
        $this->expectException(UnsupportedCalculation::class);
        $this->expectExceptionMessage("Unsupported calculation: number + string");
        $this->language->evaluate("1 + name", $this->language->scope(["name" => "aieuo"]));
    }

    public function testRoundingIsBothAPropertyAndAMethod(): void {
        $scope = $this->language->scope(["amount" => 2.345, "negative" => -2.5, "id" => 7]);
        self::assertSame(3.0, $this->language->evaluate("amount.ceil", $scope)->native());
        self::assertSame(2.0, $this->language->evaluate("amount.floor()", $scope)->native());
        self::assertSame(2.0, $this->language->evaluate("amount.round", $scope)->native());
        self::assertSame(2.0, $this->language->evaluate("amount.round()", $scope)->native());
        self::assertSame(2.35, $this->language->evaluate("amount.round(2)", $scope)->native());
        self::assertSame(2.5, $this->language->evaluate("negative.abs", $scope)->native());
        self::assertSame("007", $this->language->evaluate("id.pad(3)", $scope)->native());
    }
}
