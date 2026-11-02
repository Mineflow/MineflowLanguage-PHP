<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Types\PlainType;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[DeclareType("coin")]
final readonly class CoinValue extends Value {
    public function __toString(): string {
        return "coin";
    }
}

#[DeclareType("letters", typeHint: new ListOf(StringValue::class))]
final readonly class LettersValue extends ListValue {
    public function __construct() {
        parent::__construct([new StringValue("a"), new StringValue("b")]);
    }
}

final class ValuesTest extends TestCase {
    public function testAValueOfADeclaredTypeIsOfTheTypeItsClassDeclares(): void {
        $coin = new CoinValue();

        self::assertSame("coin", (string)$coin->type());
        self::assertInstanceOf(PlainType::class, $coin->type());
        self::assertSame(CoinValue::class, $coin->type()->class);
        self::assertSame($coin, $coin->native());
    }

    public function testADeclaredListHoldsItsOwnItemsAndAChangedCopyIsAPlainList(): void {
        $letters = new LettersValue();

        self::assertSame("letters", (string)$letters->type());
        self::assertSame(["a", "b"], $letters->native());
        foreach ([$letters->append(new StringValue("c")), $letters->with(0, new StringValue("z")), $letters->without(0)] as $copy) {
            self::assertSame(ListValue::class, $copy::class);
        }
        self::assertSame(["a", "b", "c"], $letters->append(new StringValue("c"))->native());
        self::assertSame(["b"], $letters->without(0)->native());
    }

    public function testEmptyMapDoesNotTurnIntoListAndNestedValuesRetainTypes(): void {
        $value = new MapValue(["empty" => new MapValue([]), "list" => new ListValue([]), "null" => new NullValue()]);
        self::assertSame("map", $value->item("empty")?->type()->name);
        self::assertSame("list", $value->item("list")?->type()->name);
        self::assertSame(["empty" => [], "list" => [], "null" => null], $value->native());
    }

    public function testAnItemIsAppendedAtTheEndOfAList(): void {
        $list = new ListValue([new NumberValue(1), new NumberValue(2)]);
        self::assertSame([1, 2, "a"], $list->append(new StringValue("a"))->native());
        self::assertSame("list<number|string>", (string)$list->append(new StringValue("a"))->type());
        self::assertSame([1, 2], $list->native());
    }

    public function testWithPutsAnItemInThePlaceOfTheOneUnderAKey(): void {
        $list = new ListValue([new NumberValue(1), new NumberValue(2)]);
        self::assertSame([1, 9], $list->with(1, new NumberValue(9))->native());
        self::assertSame([1, 9], $list->with("1", new NumberValue(9))->native());

        $map = new MapValue(["a" => new NumberValue(1)]);
        self::assertSame(["a" => 2], $map->with("a", new NumberValue(2))->native());
        self::assertSame(["a" => 1, "b" => 2], $map->with("b", new NumberValue(2))->native());
        self::assertSame("map", $map->with("b", new MapValue([]))->item("b")?->type()->name);
    }

    #[DataProvider("indexesAListDoesNotHave")]
    public function testWithRefusesAnIndexTheListDoesNotHave(string|int $key): void {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("The list has no item at $key; append() adds one at its end.");
        (new ListValue([new NumberValue(1), new NumberValue(2)]))->with($key, new NumberValue(9));
    }

    /**
     * @return array<string, array{string|int}>
     */
    public static function indexesAListDoesNotHave(): array {
        return ["past the end" => [2], "negative" => [-1], "a name" => ["name"], "a padded number" => ["01"]];
    }

    public function testWithoutRemovesAnItemAndKeepsAListNumbered(): void {
        $list = new ListValue([new StringValue("a"), new StringValue("b"), new StringValue("c")]);
        self::assertSame(["a", "c"], $list->without(1)->native());
        self::assertSame(["a", "b", "c"], $list->without(3)->native());
        self::assertSame("list<never>", (string)(new ListValue([new StringValue("a")]))->without(0)->type());

        $map = new MapValue(["a" => new NumberValue(1), "b" => new NumberValue(2)]);
        self::assertSame(["b" => 2], $map->without("a")->native());
        self::assertSame(["a" => 1, "b" => 2], $map->without("c")->native());
        self::assertSame("map", (new MapValue(["a" => new NumberValue(1)]))->without("a")->type()->name);
    }
}
