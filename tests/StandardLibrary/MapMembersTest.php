<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\StandardLibrary;

use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Language;
use Mineflow\Language\Runtime\Scope;
use PHPUnit\Framework\TestCase;

final class MapMembersTest extends TestCase {
    private Language $language;
    private Scope $scope;

    protected function setUp(): void {
        $this->language = Language::create();
        $this->scope = $this->language->scope([
            "user" => ["name" => "aieuo", "age" => 20],
            "prices" => ["apple" => 100, "pear" => 150],
            "ranks" => [10 => "gold", 20 => "silver", 30 => "bronze"],
        ]);
    }

    private function evaluate(string $source): mixed {
        return $this->language->evaluate($source, $this->scope)->native();
    }

    public function testOperatorsApplyToEveryValueAndKeepTheKeys(): void {
        self::assertSame(["apple" => 110.0, "pear" => 160.0], $this->evaluate("prices + 10"));
        self::assertSame(["apple" => 50.0, "pear" => 75.0], $this->evaluate("prices / 2"));
    }

    public function testTwoMapsAreCalculatedUnderTheKeysBothHave(): void {
        $scope = $this->language->scope(["prices" => ["apple" => 100, "pear" => 150], "counts" => ["pear" => 2, "apple" => 3], "sale" => ["pear" => 50, "plum" => 10]]);
        $notices = new Notices();

        self::assertSame(["apple" => 300, "pear" => 300], $this->language->evaluate("prices * counts", $scope, $notices)->native());
        self::assertSame(0, $notices->count());
        self::assertSame(["pear" => 100], $this->language->evaluate("prices - sale", $scope, $notices)->native());
        self::assertSame(1, $notices->count());
    }

    public function testReadingEntries(): void {
        self::assertSame(2, $this->evaluate("user.length"));
        self::assertSame(2, $this->evaluate("user.count()"));
        self::assertSame(["name", "age"], $this->evaluate("user.keys()"));
        self::assertSame(["aieuo", 20], $this->evaluate("user.values()"));
        self::assertSame("list", (string)$this->language->evaluate("user.values()", $this->scope)->type());
        self::assertSame("aieuo", $this->evaluate("user.first()"));
        self::assertSame(20, $this->evaluate("user.last()"));
        self::assertSame("name", $this->evaluate("user.first_key()"));
        self::assertSame("age", $this->evaluate("user.last_key()"));
        self::assertContains($this->evaluate("user.random()"), ["aieuo", 20]);
    }

    public function testReorderingKeepsTheKeys(): void {
        self::assertSame([30 => "bronze", 20 => "silver", 10 => "gold"], $this->evaluate("ranks.reverse()"));
        self::assertSame([10 => "gold"], $this->evaluate("ranks.take(1)"));
        self::assertSame([20 => "silver", 30 => "bronze"], $this->evaluate("ranks.takeLast(2)"));
        self::assertSame("map", $this->language->evaluate("ranks.take(0)", $this->scope)->type()->name);
    }

    public function testShufflingNumbersTheValuesByTheirNewPosition(): void {
        $shuffled = $this->language->evaluate("ranks.shuffled()", $this->scope);

        self::assertSame("map", $shuffled->type()->name);
        self::assertSame([0, 1, 2], array_keys($shuffled->native()));
    }

    public function testPluckReadsAPropertyOffEveryValue(): void {
        $scope = $this->language->scope(["teams" => ["red" => ["name" => "x"], "blue" => ["name" => "y"]]]);

        self::assertSame(["red" => "x", "blue" => "y"], $this->language->evaluate("teams.pluck(name)", $scope)->native());
    }
}
