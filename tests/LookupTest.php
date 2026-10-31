<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\Tests\Fixtures\Settings;
use Mineflow\Language\Tests\Fixtures\SettingsType;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Fragile {
}

#[DeclareType(Fragile::class, name: "fragile")]
final class FragileType {
    #[Property]
    public function broken(Fragile $fragile): string {
        throw new RuntimeException("broken");
    }
}

/**
 * Language::lookup(): whether a path of names reaches a value, without mistaking any other failure
 * for a missing name.
 */
final class LookupTest extends TestCase {
    private Language $language;
    private Scope $scope;

    protected function setUp(): void {
        $this->language = (new LanguageBuilder())
            ->addType(new SettingsType())
            ->addType(new FragileType())
            ->build();

        $this->scope = $this->language->scope([
            "user" => ["name" => "aieuo", "nickname" => null, "length" => 99],
            "scores" => [3, 1, 2],
            "party" => [["name" => "a"], ["name" => "b"]],
            "settings" => new Settings("config.yml", ["money" => 100]),
            "fragile" => new Fragile(),
            "{name}" => "braces",
        ])->child(["local" => "here"]);
    }

    private function lookup(string $path): mixed {
        return $this->language->lookup($path, $this->scope)?->native();
    }

    public function testAPathReadsNameAfterNameAsAnExpressionWould(): void {
        self::assertSame("here", $this->lookup("local"));
        self::assertSame("aieuo", $this->lookup("user.name"));
        self::assertSame(1, $this->lookup("scores.1"));
        self::assertSame(["a", "b"], $this->lookup("party.name"));
        self::assertSame(100, $this->lookup("settings.money"));
        self::assertSame("config.yml", $this->lookup("settings.path"));
        self::assertSame(3, $this->lookup("user.length"));
    }

    public function testAnEntryHoldingNullIsThere(): void {
        $value = $this->language->lookup("user.nickname", $this->scope);
        self::assertNotNull($value);
        self::assertNull($value->native());
    }

    public function testANameThatReachesNothingIsNull(): void {
        self::assertNull($this->language->lookup("nobody", $this->scope));
        self::assertNull($this->language->lookup("user.age", $this->scope));
        self::assertNull($this->language->lookup("user.name.first", $this->scope));
        self::assertNull($this->language->lookup("scores.3", $this->scope));
        self::assertNull($this->language->lookup("settings.debt", $this->scope));
        self::assertNull($this->language->lookup("fragile.level", $this->scope));
        self::assertNull($this->language->lookup("user..name", $this->scope));
        self::assertNull($this->language->lookup("", $this->scope));
    }

    public function testANameIsNeverEvaluated(): void {
        self::assertSame("braces", $this->lookup("{name}"));
        self::assertNull($this->language->lookup("user.{key}", $this->scope));
        self::assertNull($this->language->lookup("scores.first()", $this->scope));
    }

    public function testAFailureOfAMemberOnThePathIsNotAMissingName(): void {
        $this->expectException(ExternalFailure::class);
        $this->language->lookup("fragile.broken", $this->scope);
    }
}
