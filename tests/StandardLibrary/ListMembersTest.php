<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\StandardLibrary;

use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Language;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\StringValue;
use PHPUnit\Framework\TestCase;

final class ListMembersTest extends TestCase {
    private Language $language;
    private Scope $scope;

    protected function setUp(): void {
        $this->language = Language::create();
        $this->scope = $this->language->scope([
            "scores" => [3, 1, 2],
            "words" => ["name", "age"],
            "suffix" => "!",
            "separator" => " / ",
        ]);
    }

    private function evaluate(string $source): mixed {
        return $this->language->evaluate($source, $this->scope)->native();
    }

    public function testAHintTheItemsDoNotFitRefusesNothing(): void {
        $scope = $this->language->scope(["xs" => new ListValue([new StringValue("a")], StandardTypes::number()), "suffix" => "!"]);

        self::assertSame("a", $this->language->evaluate("xs.first()", $scope)->native());
        self::assertSame("a", $this->language->evaluate("xs.last()", $scope)->native());
        self::assertSame(["a"], $this->language->evaluate("xs.reverse()", $scope)->native());
        self::assertSame(["a!"], $this->language->evaluate("xs + suffix", $scope)->native());
    }

    public function testOperatorsApplyToEveryItem(): void {
        self::assertSame([5.0, 3.0, 4.0], $this->evaluate("scores + 2"));
        self::assertSame([1.0, -1.0, 0.0], $this->evaluate("scores - 2"));
        self::assertSame([6.0, 2.0, 4.0], $this->evaluate("scores * 2"));
        self::assertSame([1.5, 0.5, 1.0], $this->evaluate("scores / 2"));
        self::assertSame(["name!", "age!"], $this->evaluate("words + suffix"));
    }

    public function testEachItemIsCalculatedAsItWouldBeAlone(): void {
        self::assertSame(["3!", "1!", "2!"], $this->evaluate("scores + suffix"));
    }

    public function testTwoListsAreCalculatedItemByItem(): void {
        $scope = $this->language->scope(["a" => [1, 2, 3], "b" => [10, 20, 30], "names" => ["a", "b", "c"]]);
        $notices = new Notices();

        self::assertSame([11, 22, 33], $this->language->evaluate("a + b", $scope, $notices)->native());
        self::assertSame([9, 18, 27], $this->language->evaluate("b - a", $scope, $notices)->native());
        self::assertSame([10, 40, 90], $this->language->evaluate("a * b", $scope, $notices)->native());
        self::assertSame([10, 10, 10], $this->language->evaluate("b / a", $scope, $notices)->native());
        self::assertSame(["a1", "b2", "c3"], $this->language->evaluate("names + a", $scope, $notices)->native());
        self::assertSame(0, $notices->count());
    }

    public function testAListOfOneItemIsCalculatedWithEveryItemOfTheOther(): void {
        $scope = $this->language->scope(["a" => [1, 2, 3], "one" => [100]]);

        self::assertSame([101, 102, 103], $this->language->evaluate("a + one", $scope)->native());
        self::assertSame([99, 98, 97], $this->language->evaluate("one - a", $scope)->native());
    }

    public function testListsOfDifferentLengthsAreCalculatedAsFarAsTheShorterGoes(): void {
        $scope = $this->language->scope(["a" => [1, 2, 3], "short" => [5, 6], "empty" => []]);
        $notices = new Notices();

        self::assertSame([6, 8], $this->language->evaluate("a + short", $scope, $notices)->native());
        self::assertSame([], $this->language->evaluate("a + empty", $scope, $notices)->native());
        self::assertSame(array_fill(0, 2, "The collections have different keys, so only the items under keys both have are calculated."), array_map(
            static fn($notice) => $notice->message,
            $notices->all(),
        ));
    }

    public function testNestedListsAreCalculatedItemByItemAtEveryDepth(): void {
        $scope = $this->language->scope(["grid" => [[1, 2], [3, 4]], "rows" => [10, 20], "cells" => [[1, 1], [2, 2]]]);

        self::assertSame([[11, 12], [23, 24]], $this->language->evaluate("grid + rows", $scope)->native());
        self::assertSame([[1, 2], [6, 8]], $this->language->evaluate("grid * cells", $scope)->native());
    }

    public function testReadingItems(): void {
        self::assertSame(3, $this->evaluate("scores.length"));
        self::assertSame(3, $this->evaluate("scores.count()"));
        self::assertSame(3, $this->evaluate("scores.first()"));
        self::assertSame(2, $this->evaluate("scores.last()"));
        self::assertSame(0, $this->evaluate("scores.first_key()"));
        self::assertSame(2, $this->evaluate("scores.last_key()"));
        self::assertSame([0, 1, 2], $this->evaluate("scores.keys()"));
        self::assertSame("list", (string)$this->language->evaluate("scores.keys()", $this->scope)->type());
        self::assertSame([3, 1, 2], $this->evaluate("scores.values()"));
        self::assertContains($this->evaluate("scores.random()"), [3, 1, 2]);
    }

    public function testJoinWritesEveryItemAsText(): void {
        self::assertSame("3,1,2", $this->evaluate("scores.join()"));
        self::assertSame("3 / 1 / 2", $this->evaluate("scores.join({separator})"));
    }

    public function testReorderingKeepsAList(): void {
        self::assertSame([2, 1, 3], $this->evaluate("scores.reverse()"));
        self::assertSame([2, 1, 3], $this->evaluate("scores.reversed()"));
        self::assertSame([3, 1], $this->evaluate("scores.take(2)"));
        self::assertSame([1, 2], $this->evaluate("scores.take_last(2)"));
        self::assertSame([1, 2], $this->evaluate("scores.takeLast(2)"));
        self::assertSame([], $this->evaluate("scores.take_last(0)"));
        self::assertSame("list", (string)$this->language->evaluate("scores.take(2)", $this->scope)->type());

        $shuffled = $this->evaluate("scores.shuffle()");
        self::assertIsArray($shuffled);
        self::assertEqualsCanonicalizing([3, 1, 2], $shuffled);
        self::assertTrue(array_is_list($shuffled));
    }

    public function testAnEmptyListHasNoFirstItem(): void {
        $scope = $this->language->scope(["empty" => []]);
        self::assertNull($this->language->evaluate("empty.first()", $scope)->native());
        self::assertSame(0, $this->language->evaluate("empty.length", $scope)->native());
        self::assertSame("", $this->language->evaluate("empty.join()", $scope)->native());
    }

    public function testPluckReadsAPropertyOffEveryItem(): void {
        $scope = $this->language->scope([
            "players" => [["name" => "a", "length" => 1], ["name" => "bb", "length" => 2]],
            "key" => "name",
        ]);
        self::assertSame(["a", "bb"], $this->language->evaluate("players.pluck({key})", $scope)->native());
        self::assertSame([2, 2], $this->language->evaluate("players.pluck(length)", $scope)->native());
        self::assertSame([null, null], $this->language->evaluate("players.pluck(missing)", $scope)->native());
        self::assertNull($this->language->evaluate("names.pluck(missing)", $this->language->scope(["names" => ["a", "b"]]))->native());
    }
}
