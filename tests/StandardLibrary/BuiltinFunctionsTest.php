<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\StandardLibrary;

use Mineflow\Language\Language;
use PHPUnit\Framework\TestCase;

final class BuiltinFunctionsTest extends TestCase {
    private Language $language;

    protected function setUp(): void {
        $this->language = Language::create();
    }

    public function testStrAndBoolReadTheTextOfAValue(): void {
        $scope = $this->language->scope(["n" => 1, "flag" => true, "items" => [1, 2], "word" => "Yes"]);

        self::assertSame("1", $this->language->evaluate("str({n})", $scope)->native());
        self::assertSame("[1,2]", $this->language->evaluate("str({items})", $scope)->native());
        self::assertSame("abc", $this->language->evaluate("str(abc)", $scope)->native());
        self::assertSame("", $this->language->evaluate("str()", $scope)->native());
        self::assertTrue($this->language->evaluate("bool({n})", $scope)->native());
        self::assertTrue($this->language->evaluate("bool({flag})", $scope)->native());
        self::assertTrue($this->language->evaluate("bool({word})", $scope)->native());
        self::assertTrue($this->language->evaluate("bool(TRUE)", $scope)->native());
        self::assertFalse($this->language->evaluate("bool(no)", $scope)->native());
        self::assertFalse($this->language->evaluate("bool()", $scope)->native());
    }

    public function testMathFunctions(): void {
        self::assertSame(M_PI, $this->language->evaluate("pi()")->native());

        $number = $this->language->evaluate("random(1, 3)")->native();
        self::assertIsInt($number);
        self::assertGreaterThanOrEqual(1, $number);
        self::assertLessThanOrEqual(3, $number);
        self::assertSame(5, $this->language->evaluate("rand_int(5, 5)")->native());

        $fraction = $this->language->evaluate("rand_float()")->native();
        self::assertIsFloat($fraction);
        self::assertGreaterThanOrEqual(0.0, $fraction);
        self::assertLessThanOrEqual(1.0, $fraction);
    }

    public function testVarsListsWhatIsReadOutsideTheRun(): void {
        $globals = $this->language->scope(["server" => "lobby"]);
        $loop = $globals->child(["world" => "survival"])->childRun(["target" => "aieuo"])->child();
        $loop->setInRun("found", true);
        self::assertSame(["server" => "lobby", "world" => "survival"], $this->language->evaluate("vars()", $loop)->native());
    }
}
