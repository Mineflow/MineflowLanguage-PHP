<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\StandardLibrary;

use Mineflow\Language\Language;
use PHPUnit\Framework\TestCase;

final class StringMembersTest extends TestCase {
    private Language $language;

    protected function setUp(): void {
        $this->language = Language::create();
    }

    public function testOperatorsTakeTheTextOfAnyValue(): void {
        $scope = $this->language->scope(["s" => "a-b-c", "dash" => "-", "flag" => true, "nothing" => null, "items" => [1, 2], "empty" => ""]);

        self::assertSame("a-b-c1", $this->language->evaluate("s + 1", $scope)->native());
        self::assertSame("a-b-ctrue", $this->language->evaluate("s + flag", $scope)->native());
        self::assertSame("a-b-cnull", $this->language->evaluate("s + nothing", $scope)->native());
        self::assertSame("a-b-c[1,2]", $this->language->evaluate("s + items", $scope)->native());
        self::assertSame("abc", $this->language->evaluate("s - dash", $scope)->native());
        self::assertSame("a-b-c", $this->language->evaluate("s - empty", $scope)->native());
        self::assertSame("a-b-ca-b-c", $this->language->evaluate("s * 2", $scope)->native());
        self::assertSame("a-b-ca-b-c", $this->language->evaluate("2 * s", $scope)->native());
        self::assertSame("", $this->language->evaluate("s * 0", $scope)->native());
    }

    public function testCaseAndSubstring(): void {
        $scope = $this->language->scope(["text" => "Hello, World"]);

        self::assertSame(12, $this->language->evaluate("text.length()", $scope)->native());
        self::assertSame("hello, world", $this->language->evaluate("text.lowercase", $scope)->native());
        self::assertSame("hello, world", $this->language->evaluate("text.lowercase()", $scope)->native());
        self::assertSame("hello, world", $this->language->evaluate("text.toLowerCase()", $scope)->native());
        self::assertSame("HELLO, WORLD", $this->language->evaluate("text.uppercase", $scope)->native());
        self::assertSame("HELLO, WORLD", $this->language->evaluate("text.toUpperCase()", $scope)->native());
        self::assertSame("World", $this->language->evaluate("text.substring(7)", $scope)->native());
        self::assertSame("Hell", $this->language->evaluate("text.substring(0, 4)", $scope)->native());
        self::assertSame("あい", $this->language->evaluate("jp.substring(0, 2)", $this->language->scope(["jp" => "あいう"]))->native());
    }
}
