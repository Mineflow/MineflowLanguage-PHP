<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromNative;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NativeCollectionValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

final class Store {
    public int $conversions = 0;

    /**
     * @param array<string|int, mixed> $data
     */
    public function __construct(public array $data) {
    }
}

#[DeclareType("store")]
final readonly class StoreValue extends NativeCollectionValue {
    public function __construct(public Store $store) {
    }

    #[FromNative]
    public static function fromStore(Store $store): self {
        return new self($store);
    }

    protected function nativeItems(): array {
        return $this->store->data;
    }

    protected function convert(mixed $item): Value {
        $this->store->conversions++;
        return parent::convert($item);
    }

    public function native(): Store {
        return $this->store;
    }

    public function __toString(): string {
        return (string)new MapValue($this->items());
    }
}

final class NativeCollectionValueTest extends TestCase {
    private Language $language;
    private Store $store;

    protected function setUp(): void {
        $this->language = (new LanguageBuilder())->addType(StoreValue::class)->build();
        $this->store = new Store([
            "first" => ["name" => "a"],
            "money" => 100,
            "user" => ["name" => "aieuo"],
            "nothing" => null,
        ]);
    }

    private function evaluate(string $source): mixed {
        return $this->language->evaluate($source, $this->language->scope(["s" => $this->store]))->native();
    }

    public function testANameMakesOnlyTheItemUnderIt(): void {
        self::assertSame(100, $this->evaluate("s.money"));
        self::assertSame("aieuo", $this->evaluate("s.user.name"));
        self::assertSame(2, $this->store->conversions);
    }

    public function testAnItemThatIsNullIsThere(): void {
        self::assertNull($this->evaluate("s.nothing"));
        self::assertSame(1, $this->store->conversions);
    }

    public function testTheKeysAndTheirCountMakeNoItem(): void {
        self::assertSame(4, $this->evaluate("s.length"));
        self::assertSame(4, $this->evaluate("s.count()"));
        self::assertSame(["first", "money", "user", "nothing"], $this->evaluate("s.keys()"));
        self::assertSame("first", $this->evaluate("s.first_key()"));
        self::assertSame("nothing", $this->evaluate("s.last_key()"));
        self::assertSame(0, $this->store->conversions);
    }

    public function testFirstLastAndRandomMakeOneItem(): void {
        self::assertSame(["name" => "a"], $this->evaluate("s.first()"));
        self::assertNull($this->evaluate("s.last()"));
        self::assertContains($this->evaluate("s.random()"), [["name" => "a"], 100, ["name" => "aieuo"], null]);
        self::assertSame(3, $this->store->conversions);
    }

    public function testAnEmptyCollectionHasNoFirstLastOrRandomItem(): void {
        $this->store->data = [];

        self::assertNull($this->evaluate("s.first()"));
        self::assertNull($this->evaluate("s.last()"));
        self::assertNull($this->evaluate("s.random()"));
        self::assertNull($this->evaluate("s.first_key()"));
        self::assertSame(0, $this->evaluate("s.length"));
    }

    public function testANameNoItemIsUnderStopsAtTheFirstItemWithoutIt(): void {
        $this->store->data = ["money" => 100, "user" => ["name" => "aieuo"]];

        self::assertNull($this->evaluate("s.missing"));
        self::assertSame(1, $this->store->conversions);
    }

    public function testANameEveryItemHasIsReadOffEveryItem(): void {
        $this->store->data = ["a" => ["name" => "x"], "b" => ["name" => "y"]];

        self::assertSame(["a" => "x", "b" => "y"], $this->evaluate("s.name"));
        self::assertSame(2, $this->store->conversions);
    }

    public function testAMemberThatTakesEveryItemMakesThemAll(): void {
        self::assertSame([["name" => "a"], 100, ["name" => "aieuo"], null], $this->evaluate("s.values()"));
        self::assertSame(4, $this->store->conversions);
    }

    public function testANumericNameReadsTheItemUnderTheNumber(): void {
        $this->store->data = ["x", "y"];

        self::assertSame("y", $this->evaluate("s.1"));
        self::assertNull($this->evaluate("s.2"));
    }

    public function testAChangeToTheHostsDataIsSeenByTheNextRead(): void {
        $scope = $this->language->scope(["s" => $this->store]);
        self::assertSame(100, $this->language->evaluate("s.money", $scope)->native());

        $this->store->data["money"] = 250;
        self::assertSame(250, $this->language->evaluate("s.money", $scope)->native());
    }
}
