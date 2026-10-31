<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime\Engine;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\Entries;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Error\ArgumentTypeMismatch;
use Mineflow\Language\Error\UndefinedMember;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Tests\Fixtures\Settings;
use Mineflow\Language\Tests\Fixtures\SettingsType;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

/**
 * An object type with #[Index] and #[Entries], read through the names it does not declare.
 */
final class ObjectEntriesTest extends TestCase {
    private Language $language;
    private Settings $settings;

    protected function setUp(): void {
        $this->language = (new LanguageBuilder())->addType(new SettingsType())->build();
        $this->settings = new Settings("config.yml", [
            "money" => 100,
            "user" => ["name" => "aieuo"],
        ]);
    }

    private function evaluate(string $source): mixed {
        return $this->language->evaluate($source, $this->language->scope(["s" => $this->settings]))->native();
    }

    public function testAnyNameReadsTheEntryUnderIt(): void {
        self::assertSame(100, $this->evaluate("s.money"));
        self::assertSame("aieuo", $this->evaluate("s.user.name"));

        $scope = $this->language->scope(["s" => $this->settings, "key" => "money"]);
        self::assertSame(100, $this->language->evaluate("s.{key}", $scope)->native());
    }

    public function testAnEntryIsReadWhenItIsReachedNotWhenTheObjectIsPutInAScope(): void {
        $scope = $this->language->scope(["s" => $this->settings]);
        self::assertSame(100, $this->language->evaluate("s.money", $scope)->native());

        $this->settings->data["money"] = 250;
        self::assertSame(250, $this->language->evaluate("s.money", $scope)->native());
    }

    public function testADeclaredPropertyWinsOverAnEntryOfTheSameName(): void {
        $this->settings->data["path"] = "shadowed";
        self::assertSame("config.yml", $this->evaluate("s.path"));
    }

    public function testANameNoEntryHasIsReadOffEveryEntry(): void {
        $this->settings->data = ["a" => ["name" => "x"], "b" => ["name" => "y"]];
        self::assertSame(["a" => "x", "b" => "y"], $this->evaluate("s.name"));
    }

    public function testANameNeitherTheIndexNorAnyEntryHasIsUndefinedOnTheObjectType(): void {
        try {
            $this->evaluate("s.missing");

            self::fail("Expected the property to be undefined.");
        } catch (UndefinedMember $error) {
            self::assertSame("Undefined property: settings.missing", $error->getMessage());
        }
    }

    public function testAnIndexAnsweringNullHasNoEntryOfThatName(): void {
        $this->settings->data["nothing"] = null;
        $this->expectException(UndefinedMember::class);
        $this->evaluate("s.nothing");
    }

    public function testAMethodTheTypeLacksIsCalledOnItsEntries(): void {
        self::assertSame(["money", "user"], $this->evaluate("s.keys()"));
        self::assertSame(["user" => ["name" => "aieuo"], "money" => 100], $this->evaluate("s.reverse()"));
    }

    public function testAMethodTheTypeDeclaresWinsOverOneOfItsEntries(): void {
        self::assertSame("declared", $this->evaluate("s.first()"));
        self::assertSame($this->settings->data, $this->evaluate("s.all()"));
    }

    public function testAMethodNeitherHasIsUndefinedOnTheObjectType(): void {
        try {
            $this->evaluate("s.missing()");
            self::fail("Expected the method to be undefined.");
        } catch (UndefinedMember $error) {
            self::assertSame("Undefined method: settings.missing", $error->getMessage());
        }
    }

    public function testAPropertyOfTheCollectionWinsOverAnEntryOfTheSameName(): void {
        $this->settings->data["length"] = "long";
        self::assertSame(3, $this->evaluate("s.length"));
        self::assertSame(3, $this->language->evaluate("m.length", $this->language->scope(["m" => $this->settings->data]))->native());
    }

    public function testAnObjectTypeIsCalculatedAsItsEntries(): void {
        $this->settings->data = ["a" => 1, "b" => 2];
        self::assertSame(["a" => 11.0, "b" => 12.0], $this->evaluate("s + 10"));
        self::assertSame(["a" => 2.0, "b" => 4.0], $this->evaluate("s * 2"));
        $scope = $this->language->scope(["s" => $this->settings, "label" => "total: "]);
        self::assertSame("total: <a:1,b:2>", $this->language->evaluate("label + s", $scope)->native());
    }

    public function testAnObjectTypeIsNotCalculatedWithACollection(): void {
        $scope = $this->language->scope(["s" => $this->settings, "list" => [1, 2]]);
        foreach (["list + s" => "list<number> + settings", "s + list" => "settings + list<number>", "s + s" => "settings + settings"] as $source => $types) {
            try {
                $this->language->evaluate($source, $scope);
                self::fail("Expected $source to be unsupported.");
            } catch (UnsupportedCalculation $error) {
                self::assertSame("Unsupported calculation: $types", $error->getMessage(), $source);
            }
        }
    }

    public function testAnOperatorTheObjectTypeDeclaresWinsOverOneOfItsEntries(): void {
        $extension = new #[ExtendType(Settings::class)]
        class {
            #[Operator(BinaryOperator::Add)]
            public function merge(Settings $settings, string $path): Settings {
                return new Settings($path, $settings->data);
            }
        };
        $language = (new LanguageBuilder())->addType(new SettingsType())->addExtension($extension)->build();
        $scope = $language->scope(["s" => $this->settings, "path" => "b.yml"]);
        self::assertSame("b.yml", $language->evaluate("(s + path).path", $scope)->native());
    }

    public function testAnObjectTypeReadsAsItsEntries(): void {
        self::assertSame("<money:100,user:<name:aieuo>>", $this->language->render("{s}", $this->language->scope(["s" => $this->settings])));
        $scope = $this->language->scope(["all" => [$this->settings, new Settings("b.yml", ["money" => 5])]]);
        self::assertSame("[<money:100,user:<name:aieuo>>,<money:5>]", $this->language->render("{all}", $scope));
    }

    public function testAnObjectTypeIsPassedWhereItsCollectionIsTaken(): void {
        $functions = new class {
            /**
             * @param array<string, mixed> $map
             */
            #[Method]
            public function size(#[Param(type: new MapOf(Special::Mixed))] array $map): int {
                return count($map);
            }
        };
        $language = (new LanguageBuilder())->addType(new SettingsType())->addFunctions($functions)->build();
        self::assertSame(2, $language->evaluate("size({s})", $language->scope(["s" => $this->settings]))->native());
    }

    public function testAMemberGetsTheObjectOnlyWhenItNamesItsType(): void {
        $functions = new class {
            #[Method]
            public function kind(#[Param(Special::Mixed)] Value $value): string {
                return $value instanceof CollectionValue ? "collection" : (string)$value->type;
            }

            #[Method]
            public function where(Settings $settings): string {
                return $settings->path;
            }

            #[Method]
            public function shout(string $text): string {
                return strtoupper($text);
            }
        };
        $language = (new LanguageBuilder())->addType(new SettingsType())->addFunctions($functions)->build();
        $scope = $language->scope(["s" => $this->settings]);

        self::assertSame("collection", $language->evaluate("kind({s})", $scope)->native());
        self::assertSame("config.yml", $language->evaluate("where({s})", $scope)->native());
        try {
            $language->evaluate("shout({s})", $scope);
            self::fail("Expected the argument to be refused.");
        } catch (ArgumentTypeMismatch $error) {
            self::assertSame("settings", (string)$error->actual);
        }
    }

    public function testATypeWithEntriesAloneReadsANameAsTheEntryOfIt(): void {
        $language = (new LanguageBuilder())->addType(new #[DeclareType(Settings::class, name: "settings")]
        class {
            /**
             * @return array<string, mixed>
             */
            #[Entries(type: new MapOf(Special::Mixed, Builtin::String))]
            public function all(Settings $settings): array {
                return $settings->data;
            }
        })->build();
        $this->settings->data["team"] = ["a" => ["name" => "x"], "b" => ["name" => "y"]];
        $scope = $language->scope(["s" => $this->settings]);

        self::assertSame(100, $language->evaluate("s.money", $scope)->native());
        self::assertSame(["a" => "x", "b" => "y"], $language->evaluate("s.team.name", $scope)->native());
        self::assertSame(["money", "user", "team"], $language->evaluate("s.keys()", $scope)->native());
        $this->expectException(UndefinedMember::class);
        $language->evaluate("s.missing", $scope);
    }
}
