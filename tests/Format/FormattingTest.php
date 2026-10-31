<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Format;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Format\DefaultValueFormatter;
use Mineflow\Language\Format\ValueFormatter;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Values\BooleanValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;

final class HostFormatter implements ValueFormatter {
    public function format(Value $value): string {
        return match (true) {
            $value instanceof BooleanValue => $value->value ? "yes" : "no",
            $value instanceof ListValue => implode("; ", array_map($this->format(...), $value->items())),
            default => (new DefaultValueFormatter())->format($value),
        };
    }
}

final class QuotedStrings implements ValueFormatter {
    public function format(Value $value): string {
        $text = (new DefaultValueFormatter())->format($value);
        return $value instanceof StringValue ? "\"$text\"" : $text;
    }
}

final class BrokenFormatter implements ValueFormatter {
    public function format(Value $value): string {
        throw new RuntimeException("the formatter blew up");
    }
}

final class Cursed implements Stringable {
    public function __toString(): string {
        throw new RuntimeException("the object blew up");
    }
}

#[DeclareType(Cursed::class, name: "cursed")]
final class CursedType {
}

final class Rank implements Stringable {
    public function __construct(private int $place) {
    }

    public function __toString(): string {
        return "#{$this->place}";
    }
}

#[DeclareType(Rank::class, name: "rank")]
final class RankType {
}

final class FormattingTest extends TestCase {
    public function testARegisteredObjectIsWrittenThroughItsOwnStringForm(): void {
        $language = (new LanguageBuilder())->addType(new RankType())->build();
        $scope = $language->scope(["rank" => new Rank(2)]);

        self::assertSame("#2", $language->render("{rank}", $scope));
        self::assertSame("#2", $language->text($scope->get("rank")));
    }

    public function testTheDefaultFormatterIsTheOneThisLanguageWrites(): void {
        $language = Language::create();
        $scope = $language->scope(["flags" => [true, false], "user" => ["a" => 1]]);

        self::assertSame("[true,false]", $language->render("{flags}", $scope));
        self::assertSame("<a:1>", $language->render("{user}", $scope));
        self::assertSame("true,false", $language->evaluate("flags.join()", $scope)->native());
    }

    public function testAHostFormatterReplacesItWhereverTextIsProduced(): void {
        $language = (new LanguageBuilder())->withFormatter(new HostFormatter())->build();
        $scope = $language->scope(["flags" => [true, false]]);

        self::assertSame("yes; no", $language->render("{flags}", $scope));
        self::assertSame("yes,no", $language->evaluate("flags.join()", $scope)->native());
        self::assertSame("yes; no", $language->text($scope->get("flags")));
    }

    public function testAHostFormatterWritesOnlyTheHolesOfATemplate(): void {
        $language = (new LanguageBuilder())->withFormatter(new QuotedStrings())->build();
        $scope = $language->scope(["name" => "aieuo"]);

        self::assertSame("plain", $language->render("plain"));
        self::assertSame("hi \"aieuo\"!", $language->render("hi {name}!", $scope));
        self::assertSame("\"aieuo\"", $language->render($language->parseExpression("name"), $scope));
    }

    public function testAHostFormatterDoesNotChangeWhatANameReaches(): void {
        $language = (new LanguageBuilder())->withFormatter(new QuotedStrings())->build();
        $scope = $language->scope(["user" => ["name" => "aieuo"], "key" => "name", "which" => "key", "scores" => [3, 1]]);

        self::assertSame("aieuo", $language->evaluate("user.name", $scope)->native());
        self::assertSame("aieuo", $language->evaluate("user.{key}", $scope)->native());
        self::assertSame("name", $language->evaluate("{which}", $scope)->native());
        self::assertSame(3, $language->evaluate("scores.first()", $scope)->native());
    }

    public function testAFormatterThatThrowsIsAnExternalFailure(): void {
        $failures = [
            "the formatter blew up" => [(new LanguageBuilder())->withFormatter(new BrokenFormatter())->build(), "aieuo"],
            "the object blew up" => [(new LanguageBuilder())->addType(new CursedType())->build(), new Cursed()],
        ];

        foreach ($failures as $message => [$language, $x]) {
            try {
                $language->render("hi {x}", $language->scope(["x" => $x]));

                self::fail("Rendering should have failed.");
            } catch (ExternalFailure $error) {
                self::assertSame($message, $error->getMessage());
                self::assertNull($error->reference);
                self::assertSame([0, 6], [$error->start(), $error->end()]);
            }
        }
    }
}
