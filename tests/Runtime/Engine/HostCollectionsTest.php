<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime\Engine;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Index;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\ArgumentTypeMismatch;
use Mineflow\Language\Error\UndefinedMember;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Tests\Fixtures\Settings;
use Mineflow\Language\Tests\Fixtures\SettingsValue;
use Mineflow\Language\Types\Expression\CollectionOf;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\TypeDeclarations;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

/**
 * A map of its own: it holds its items, so it is a map wherever one is taken.
 */
#[DeclareType("scores", typeHint: new MapOf(NumberValue::class, StringValue::class))]
final readonly class ScoresValue extends MapValue {
    /**
     * @param array<string, int> $scores
     */
    public function __construct(array $scores) {
        parent::__construct(array_map(static fn(int $score) => new NumberValue($score), $scores));
    }
}

/**
 * A map whose index replaces the one of maps: a name reads only what the index gives.
 */
#[DeclareType("shouting")]
final readonly class ShoutingValue extends MapValue {
    #[Index(description: "The item under the name, in upper case.")]
    public function shouted(string $name): ?string {
        $item = $this->item($name);
        return $item instanceof StringValue ? strtoupper($item->value) : null;
    }
}

/**
 * A collection type over a PHP object of the host: a CollectionValue subclass that reads its items from the object.
 */
final class HostCollectionsTest extends TestCase {
    private Language $language;
    private Settings $settings;

    protected function setUp(): void {
        $this->language = (new LanguageBuilder())->addType(SettingsValue::class)->build();
        $this->settings = new Settings("config.yml", [
            "money" => 100,
            "user" => ["name" => "aieuo"],
        ]);
    }

    private function evaluate(string $source): mixed {
        return $this->language->evaluate($source, $this->language->scope(["s" => new SettingsValue($this->settings)]))->native();
    }

    public function testTheTypeIsACollectionButNotAMap(): void {
        $type = (new SettingsValue($this->settings))->type();

        self::assertSame("settings", (string)$type);
        self::assertTrue(TypeDeclarations::of(CollectionValue::class)->accepts($type));
        self::assertTrue(StandardTypes::collection(key: StandardTypes::string())->accepts($type));
        self::assertFalse(StandardTypes::collection(key: StandardTypes::number())->accepts($type));
        self::assertFalse(StandardTypes::map(Type::mixed())->accepts($type));
        self::assertFalse(StandardTypes::list(Type::mixed())->accepts($type));
        self::assertSame(["settings", "collection"], [
            "settings",
            ...array_map(static fn($ancestor) => $ancestor->name, $this->language->catalog->types->ancestors("settings")),
        ]);
    }

    public function testAnyNameReadsTheItemUnderIt(): void {
        self::assertSame(100, $this->evaluate("s.money"));
        self::assertSame("aieuo", $this->evaluate("s.user.name"));

        $scope = $this->language->scope(["s" => new SettingsValue($this->settings), "key" => "money"]);
        self::assertSame(100, $this->language->evaluate("s.{key}", $scope)->native());
    }

    public function testAnItemIsReadWhenItIsReachedNotWhenTheValueIsPutInAScope(): void {
        $scope = $this->language->scope(["s" => new SettingsValue($this->settings)]);
        self::assertSame(100, $this->language->evaluate("s.money", $scope)->native());

        $this->settings->data["money"] = 250;
        self::assertSame(250, $this->language->evaluate("s.money", $scope)->native());
    }

    public function testADeclaredPropertyWinsOverAnItemOfTheSameName(): void {
        $this->settings->data["path"] = "shadowed";
        self::assertSame("config.yml", $this->evaluate("s.path"));
    }

    public function testANameNoItemHasIsReadOffEveryItem(): void {
        $this->settings->data = ["a" => ["name" => "x"], "b" => ["name" => "y"]];
        self::assertSame(["a" => "x", "b" => "y"], $this->evaluate("s.name"));
        self::assertSame(MapValue::class, $this->language->evaluate("s.name", $this->language->scope(["s" => new SettingsValue($this->settings)]))::class);
    }

    public function testANameNeitherAnItemNorEveryItemHasReadsAsNull(): void {
        self::assertNull($this->evaluate("s.missing"));

        $this->settings->data = ["a" => 1];
        self::assertNull($this->evaluate("s.missing"));
    }

    public function testAnItemThatIsNullIsThere(): void {
        $this->settings->data["nothing"] = null;
        self::assertNull($this->evaluate("s.nothing"));
    }

    public function testTheCollectionsMembersAreInheritedButNotThoseOfAMap(): void {
        self::assertSame(["money", "user"], $this->evaluate("s.keys()"));
        self::assertSame(2, $this->evaluate("s.count()"));
        self::assertSame("collection.keys", (string)$this->language->catalog->origin(MemberReference::method("settings", "keys")));

        $this->expectException(UndefinedMember::class);
        $this->expectExceptionMessage("Undefined method: settings.reverse");
        $this->evaluate("s.reverse()");
    }

    public function testAMethodTheTypeDeclaresWinsOverOneOfTheCollection(): void {
        self::assertSame("declared", $this->evaluate("s.first()"));
        self::assertSame($this->settings->data, $this->evaluate("s.all()"));
    }

    public function testAMethodNeitherHasIsUndefinedOnTheType(): void {
        try {
            $this->evaluate("s.missing()");
            self::fail("Expected the method to be undefined.");
        } catch (UndefinedMember $error) {
            self::assertSame("Undefined method: settings.missing", $error->getMessage());
        }
    }

    public function testAPropertyOfTheCollectionWinsOverAnItemOfTheSameName(): void {
        $this->settings->data["length"] = "long";
        self::assertSame(3, $this->evaluate("s.length"));
        self::assertSame(3, $this->language->evaluate("m.length", $this->language->scope(["m" => $this->settings->data]))->native());
    }

    public function testTheTypeHasOnlyTheOperatorsItDeclares(): void {
        $scope = $this->language->scope(["s" => new SettingsValue($this->settings), "list" => [1, 2], "label" => "total: "]);
        self::assertSame("total: <money:100,user:<name:aieuo>>", $this->language->evaluate("label + s", $scope)->native());

        foreach (["s + 10" => "settings + number", "list + s" => "list<number> + settings", "s + list" => "settings + list<number>", "s + s" => "settings + settings"] as $source => $types) {
            try {
                $this->language->evaluate($source, $scope);
                self::fail("Expected $source to be unsupported.");
            } catch (UnsupportedCalculation $error) {
                self::assertSame("Unsupported calculation: $types", $error->getMessage(), $source);
            }
        }
    }

    public function testAnExtensionAddsAnOperator(): void {
        $extension = new #[ExtendType(SettingsValue::class)]
        class {
            #[Operator(BinaryOperator::Add)]
            public function merge(SettingsValue $settings, string $path): SettingsValue {
                return new SettingsValue(new Settings($path, $settings->settings->data));
            }
        };
        $language = (new LanguageBuilder())->addType(SettingsValue::class)->addExtension($extension)->build();
        $scope = $language->scope(["s" => new SettingsValue($this->settings), "path" => "b.yml"]);
        self::assertSame("b.yml", $language->evaluate("(s + path).path", $scope)->native());
    }

    public function testTheTypeIsWrittenAsItsOwnTextSays(): void {
        self::assertSame("<money:100,user:<name:aieuo>>", $this->language->render("{s}", $this->language->scope(["s" => new SettingsValue($this->settings)])));
        $scope = $this->language->scope(["all" => [new SettingsValue($this->settings), new SettingsValue(new Settings("b.yml", ["money" => 5]))]]);
        self::assertSame("[<money:100,user:<name:aieuo>>,<money:5>]", $this->language->render("{all}", $scope));
    }

    public function testTheTypeIsPassedWhereACollectionIsTakenButNotWhereAMapIs(): void {
        $functions = new class {
            /**
             * @param array<string, mixed> $items
             */
            #[Method]
            public function size(#[Param(type: new CollectionOf(Special::Mixed))] array $items): int {
                return count($items);
            }

            #[Method]
            public function entries(CollectionValue $collection): int {
                return count($collection->items());
            }

            #[Method]
            public function mapSize(MapValue $map): int {
                return count($map->items());
            }
        };
        $language = (new LanguageBuilder())->addType(SettingsValue::class)->addFunctions($functions)->build();
        $scope = $language->scope(["s" => new SettingsValue($this->settings)]);
        self::assertSame(2, $language->evaluate("size({s})", $scope)->native());
        self::assertSame(2, $language->evaluate("entries({s})", $scope)->native());

        $this->expectException(ArgumentTypeMismatch::class);
        $language->evaluate("mapSize({s})", $scope);
    }

    public function testThePhpObjectConvertsToItsValueWhereverTheLanguageTakesIt(): void {
        $functions = new class {
            #[Method]
            public function where(Settings $settings): string {
                return $settings->path;
            }

            #[Method]
            public function other(): Settings {
                return new Settings("other.yml", ["money" => 1]);
            }
        };
        $language = (new LanguageBuilder())->addType(SettingsValue::class)->addFunctions($functions)->build();
        $scope = $language->scope(["s" => $this->settings]);

        self::assertInstanceOf(SettingsValue::class, $scope->get("s"));
        self::assertSame("config.yml", $language->evaluate("where({s})", $scope)->native());
        self::assertSame("other.yml", $language->evaluate("other().path", $scope)->native());
        self::assertSame("settings", (string)$language->catalog->definition(MemberReference::function("where"))?->parameters[0]->type);
    }

    public function testEveryMemberGetsTheSameValue(): void {
        $functions = new class {
            #[Method]
            public function kind(Value $value): string {
                return $value instanceof CollectionValue ? "collection ".$value::class : (string)$value->type();
            }

            #[Method]
            public function where(SettingsValue $settings): string {
                return $settings->settings->path;
            }

            #[Method]
            public function shout(string $text): string {
                return strtoupper($text);
            }
        };
        $language = (new LanguageBuilder())->addType(SettingsValue::class)->addFunctions($functions)->build();
        $scope = $language->scope(["s" => new SettingsValue($this->settings)]);

        self::assertSame("collection ".SettingsValue::class, $language->evaluate("kind({s})", $scope)->native());
        self::assertSame("config.yml", $language->evaluate("where({s})", $scope)->native());
        try {
            $language->evaluate("shout({s})", $scope);
            self::fail("Expected the argument to be refused.");
        } catch (ArgumentTypeMismatch $error) {
            self::assertSame("settings", (string)$error->actual);
        }
    }

    public function testAMapOfItsOwnIsAMap(): void {
        $language = (new LanguageBuilder())->addType(ScoresValue::class)->build();
        $scores = new ScoresValue(["aieuo" => 3, "tanaka" => 1]);
        $scope = $language->scope(["s" => $scores]);

        self::assertSame("scores", (string)$scores->type());
        self::assertTrue(StandardTypes::map(StandardTypes::number(), StandardTypes::string())->accepts($scores->type()));
        self::assertSame(3, $language->evaluate("s.aieuo", $scope)->native());
        self::assertSame(["tanaka" => 1, "aieuo" => 3], $language->evaluate("s.reverse()", $scope)->native());
        self::assertNull($language->evaluate("s.missing", $scope)->native());
    }

    public function testAnIndexOfTheTypeReplacesTheOneItInherits(): void {
        $language = (new LanguageBuilder())->addType(ShoutingValue::class)->build();
        $scope = $language->scope(["s" => new ShoutingValue(["name" => new StringValue("aieuo"), "user" => new MapValue(["name" => new StringValue("x")])])]);

        self::assertSame("AIEUO", $language->evaluate("s.name", $scope)->native());
        self::assertSame("shouting.*", (string)$language->catalog->origin(MemberReference::index("shouting")));
        self::assertNull($language->evaluate("s.user", $scope)->native());
    }
}
