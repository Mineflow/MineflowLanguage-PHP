<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Types;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Expression\Nullable;
use Mineflow\Language\Types\Expression\OneOf;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

#[DeclareType("player")]
final readonly class PlayerValue extends Value {
    public function __construct(public string $name) {
    }

    #[Property]
    public function name(): string {
        return $this->name;
    }

    /**
     * @return array<string, PlayerValue>
     */
    #[Method(type: new MapOf(PlayerValue::class, StringValue::class))]
    public function team(): array {
        return ["self" => $this];
    }

    /**
     * @return list<PlayerValue>
     */
    #[Method(type: new ListOf(PlayerValue::class))]
    public function party(): array {
        return [$this];
    }

    public function __toString(): string {
        return "player";
    }
}

#[DeclareType("roster", typeHint: new ListOf(PlayerValue::class))]
final readonly class RosterValue extends ListValue {
    public function __construct() {
        parent::__construct([new PlayerValue("aieuo")]);
    }
}

#[ExtendType(ListValue::class)]
final class ListExtras {
    #[Method(type: new OneOf(ListValue::class, MapValue::class, NullValue::class))]
    public function nothing(Value $value): Value {
        return new NullValue();
    }

    #[Method(type: new ListOf(Special::Mixed))]
    public function anything(Value $value): Value {
        return $value;
    }
}

final class Functions {
    #[Method(type: new MapOf(Special::Mixed, StringValue::class))]
    public function settings(): MapValue {
        return new MapValue(["a" => new NumberValue(1)]);
    }
}

final class TypeExpressionsTest extends TestCase {
    public function testExpressionsResolveToLanguageTypes(): void {
        $catalog = (new LanguageBuilder())
            ->addType(PlayerValue::class)
            ->addType(RosterValue::class)
            ->addExtension(new ListExtras())
            ->addFunctions(new Functions())
            ->build()->catalog;

        $first = $catalog->definition(MemberReference::method("list", "first"));
        self::assertSame("\$Element|null", (string)$first?->returns);
        self::assertSame("null|string", (string)$first?->resultType(StandardTypes::list(StandardTypes::string())));
        self::assertSame("list<\$Key>", (string)$catalog->definition(MemberReference::method("list", "keys"))?->returns);
        self::assertSame("list|map|null", (string)$catalog->definition(MemberReference::method("list", "nothing"))?->returns);
        self::assertSame("list", (string)$catalog->definition(MemberReference::method("list", "anything"))?->returns);
        self::assertSame("map<string,player>", (string)$catalog->definition(MemberReference::method("player", "team"))?->returns);
        self::assertSame("list<player>", (string)$catalog->definition(MemberReference::method("player", "party"))?->returns);
        self::assertSame("map<string,mixed>", (string)$catalog->definition(MemberReference::function("settings"))?->returns);
        self::assertSame("null|player", (string)$first?->resultType($catalog->types->named("roster")));
        self::assertSame("null|player", (string)$first?->resultType((new RosterValue())->type()));
        self::assertSame("collection.first", (string)$catalog->origin(MemberReference::method("roster", "first")));
    }

    public function testPlaceholdersAreOnlyAvailableForCollections(): void {
        $extension = new #[ExtendType(NumberValue::class)]
        class {
            #[Method(type: new Nullable(Placeholder::Element))]
            public function first(int|float $value): Value {
                return new NumberValue($value);
            }
        };
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Placeholder::Element is only available in member declarations of a collection.");
        (new LanguageBuilder())->addExtension($extension)->build();
    }

    public function testUnregisteredClassesAreRejectedWhenBuilding(): void {
        $extension = new #[ExtendType(NumberValue::class)]
        class {
            #[Method(type: PlayerValue::class)]
            public function owner(int|float $value): Value {
                return new NullValue();
            }
        };
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Unregistered PHP type: ".PlayerValue::class);
        (new LanguageBuilder())->addExtension($extension)->build();
    }
}
