<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[DeclareType("cursed")]
final readonly class CursedValue extends Value {
    public function __toString(): string {
        throw new RuntimeException("the object blew up");
    }
}

#[DeclareType("rank")]
final readonly class RankValue extends Value {
    public function __construct(private int $place) {
    }

    public function __toString(): string {
        return "#{$this->place}";
    }
}

#[DeclareType("greeting")]
final readonly class GreetingValue extends ListValue {
    public function __construct(string $name) {
        parent::__construct([new StringValue("hi"), new StringValue($name)]);
    }
}

/**
 * The text of a value is what its __toString() writes, wherever the language writes it.
 */
final class TextTest extends TestCase {
    public function testARegisteredObjectIsWrittenThroughItsOwnStringForm(): void {
        $language = (new LanguageBuilder())->addType(RankValue::class)->build();
        $scope = $language->scope(["rank" => new RankValue(2)]);

        self::assertSame("#2", $language->render("{rank}", $scope));
        self::assertSame("#2", (string)$scope->get("rank"));
    }

    public function testADeclaredListIsWrittenAsItsItems(): void {
        $language = (new LanguageBuilder())->addType(GreetingValue::class)->build();
        $scope = $language->scope(["g" => new GreetingValue("aieuo")]);

        self::assertSame("[hi,aieuo]", (string)$scope->get("g"));
        self::assertSame("hi,aieuo", $language->evaluate("g.join()", $scope)->native());
    }

    public function testTheStandardTypesWriteTheirOwnText(): void {
        $language = Language::create();
        $scope = $language->scope(["flags" => [true, false], "user" => ["a" => 1], "nothing" => null]);

        self::assertSame("[true,false]", $language->render("{flags}", $scope));
        self::assertSame("<a:1>", $language->render("{user}", $scope));
        self::assertSame("true,false", $language->evaluate("flags.join()", $scope)->native());
        self::assertSame("", $language->evaluate("str()")->native());
    }

    public function testTheItemsOfACollectionAreWrittenTheSameWayAsTheCollection(): void {
        $language = (new LanguageBuilder())->addType(RankValue::class)->build();
        $scope = $language->scope(["ranks" => [new RankValue(1), new RankValue(2)], "byName" => ["aieuo" => new RankValue(3)]]);

        self::assertSame("[#1,#2]", $language->render("{ranks}", $scope));
        self::assertSame("#1;#2", $language->render("{ranks.join(;)}", $scope));
        self::assertSame("<aieuo:#3>", $language->render("{byName}", $scope));
    }

    public function testAComputedNameIsTheTextOfItsValue(): void {
        $language = (new LanguageBuilder())->addType(RankValue::class)->build();
        $scope = $language->scope(["places" => ["#1" => "gold"], "first" => new RankValue(1)]);

        self::assertSame("gold", $language->evaluate("places.{first}", $scope)->native());
    }

    public function testWhatAStringFormThrowsReachesTheCallerAsItIs(): void {
        $language = (new LanguageBuilder())->addType(CursedValue::class)->build();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("the object blew up");
        $language->render("hi {x}", $language->scope(["x" => new CursedValue()]));
    }

    public function testWhatAStringFormThrowsInsideAMemberReachesTheCallerAsItIs(): void {
        $language = (new LanguageBuilder())->addType(CursedValue::class)->build();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("the object blew up");
        $language->evaluate("xs.join()", $language->scope(["xs" => [new CursedValue()]]));
    }
}
