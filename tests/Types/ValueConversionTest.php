<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Types;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromValue;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

#[DeclareType("color")]
final readonly class ColorValue extends Value {
    public function __construct(public string $name) {
    }

    #[FromValue]
    public static function fromValue(Value $value, Notices $notices): ?self {
        if (!$value instanceof StringValue) {
            return null;
        }
        if (!in_array($value->value, ["red", "blue"], true)) {
            $notices->add("$value is no color, so red is used.");
            return new self("red");
        }
        return new self($value->value);
    }

    public function __toString(): string {
        return $this->name;
    }
}

#[DeclareType("misdeclared")]
final readonly class MisdeclaredValue extends Value {
    #[FromValue]
    public static function fromValue(Value $value): self {
        return new self();
    }

    public function __toString(): string {
        return "";
    }
}

final class Painter {
    #[Method]
    public function paint(ColorValue $color): string {
        return "painted $color";
    }

    #[Method]
    public function either(ColorValue|int $choice): string {
        return $choice instanceof ColorValue ? "color $choice" : "number $choice";
    }
}

final class ValueConversionTest extends TestCase {
    public function testAHostTypeIsMadeByItsFromValueWhereItIsExpected(): void {
        $language = (new LanguageBuilder())->addType(ColorValue::class)->addFunctions(new Painter())->build();
        $notices = new Notices();

        self::assertSame("painted blue", $language->evaluate("paint(blue)", null, $notices)->native());
        self::assertSame(0, $notices->count());

        self::assertSame("painted red", $language->evaluate("paint(green)", null, $notices)->native());
        self::assertSame(["color of paint: green is no color, so red is used."], array_map(static fn($notice) => $notice->message, $notices->all()));
    }

    public function testAUnionTakesTheMemberThatConvertsWithoutANotice(): void {
        $language = (new LanguageBuilder())->addType(ColorValue::class)->addFunctions(new Painter())->build();
        $notices = new Notices();

        self::assertSame("color blue", $language->evaluate("either(blue)", null, $notices)->native());
        self::assertSame("number 3", $language->evaluate("either(3)", null, $notices)->native());
        self::assertSame([], $notices->all());

        // Neither converts green without a notice, so the first member that converts it at all is used.
        self::assertSame("color red", $language->evaluate("either(green)", null, $notices)->native());
        self::assertCount(1, $notices->all());
    }

    public function testAFromValueMethodHasToTakeTheValueAndTheNotices(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage('#[FromValue] goes on a public static method fromValue(Value $value, Notices $notices): ?self: '.MisdeclaredValue::class."::fromValue()");

        (new LanguageBuilder())->addType(MisdeclaredValue::class)->build();
    }
}
