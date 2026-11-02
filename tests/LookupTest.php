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
use Mineflow\Language\Tests\Fixtures\SettingsValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[DeclareType("fragile")]
final readonly class FragileValue extends Value {
    #[Property]
    public function broken(): string {
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
            ->addType(SettingsValue::class)
            ->addType(FragileValue::class)
            ->build();

        $this->scope = $this->language->scope([
            "user" => ["name" => "aieuo", "nickname" => null, "length" => 99],
            "scores" => [3, 1, 2],
            "party" => [["name" => "a"], ["name" => "b"]],
            "settings" => new SettingsValue(new Settings("config.yml", ["money" => 100])),
            "fragile" => new FragileValue(),
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
        self::assertNull($this->language->lookup("user.name.first", $this->scope));
        self::assertNull($this->language->lookup("fragile.level", $this->scope));
        self::assertNull($this->language->lookup("user..name", $this->scope));
        self::assertNull($this->language->lookup("", $this->scope));
    }

    public function testANameAnIndexHasNothingUnderReadsAsNull(): void {
        foreach (["user.age", "scores.3", "settings.debt"] as $path) {
            $value = $this->language->lookup($path, $this->scope);
            self::assertNotNull($value, $path);
            self::assertNull($value->native(), $path);
        }
    }

    public function testANameIsNeverEvaluated(): void {
        self::assertSame("braces", $this->lookup("{name}"));
        self::assertNull($this->lookup("user.{key}"));
        self::assertNull($this->lookup("scores.first()"));
        self::assertNull($this->language->lookup("local.{key}", $this->scope));
    }

    public function testAFailureOfAMemberOnThePathIsNotAMissingName(): void {
        $this->expectException(ExternalFailure::class);
        $this->language->lookup("fragile.broken", $this->scope);
    }
}
