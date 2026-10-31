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
use Mineflow\Language\MapValue;
use Mineflow\Language\NumberValue;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Expression\Nullable;
use Mineflow\Language\Types\Expression\ObjectOf;
use Mineflow\Language\Types\Expression\OneOf;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Value;
use PHPUnit\Framework\TestCase;

final class Player {
    public function __construct(public string $name) {
    }
}

#[ExtendType(Builtin::List)]
final class ListExtras {
    #[Method(type: new OneOf(Builtin::List, Builtin::Map, Builtin::Null))]
    public function nothing(Value $value): Value {
        return Value::from(null);
    }

    #[Method(type: new ListOf(Special::Mixed))]
    public function anything(Value $value): Value {
        return $value;
    }
}

#[DeclareType(Player::class, name: "player")]
final class PlayerType {
    #[Property]
    public function name(Player $player): string {
        return $player->name;
    }

    /**
     * @return array<string, Player>
     */
    #[Method(type: new MapOf(new ObjectOf(Player::class), Builtin::String))]
    public function team(Player $player): array {
        return ["self" => $player];
    }

    /**
     * @return list<Player>
     */
    #[Method(type: new ListOf(new ObjectOf(Player::class)))]
    public function party(Player $player): array {
        return [$player];
    }
}

final class Functions {
    #[Method(type: new MapOf(Special::Mixed, Builtin::String))]
    public function settings(): MapValue {
        return new MapValue(["a" => new NumberValue(1)]);
    }
}

final class TypeExpressionsTest extends TestCase {
    public function testExpressionsResolveToLanguageTypes(): void {
        $catalog = (new LanguageBuilder())
            ->addType(new PlayerType())
            ->addExtension(new ListExtras())
            ->addFunctions(new Functions())
            ->build()->catalog;

        $first = $catalog->definition(MemberReference::method("list", "first"));
        self::assertSame("\$Element|null", (string)$first?->returns);
        self::assertSame("null|string", (string)$first?->resultType(Type::list(Type::string())));
        self::assertSame("list<\$Key>", (string)$catalog->definition(MemberReference::method("list", "keys"))?->returns);
        self::assertSame("list|map|null", (string)$catalog->definition(MemberReference::method("list", "nothing"))?->returns);
        self::assertSame("list", (string)$catalog->definition(MemberReference::method("list", "anything"))?->returns);
        self::assertSame("map<string,player>", (string)$catalog->definition(MemberReference::method("player", "team"))?->returns);
        self::assertSame("list<player>", (string)$catalog->definition(MemberReference::method("player", "party"))?->returns);
        self::assertSame("map<string,mixed>", (string)$catalog->definition(MemberReference::function("settings"))?->returns);
    }

    public function testPlaceholdersAreOnlyAvailableForCollections(): void {
        $extension = new #[ExtendType(Builtin::Number)]
        class {
            #[Method(type: new Nullable(Placeholder::Element))]
            public function first(int|float $value): Value {
                return Value::from($value);
            }
        };
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Placeholder::Element is only available in list/map member declarations.");
        (new LanguageBuilder())->addExtension($extension)->build();
    }

    public function testUnregisteredClassesAreRejectedWhenBuilding(): void {
        $extension = new #[ExtendType(Builtin::Number)]
        class {
            #[Method(type: new ObjectOf(Player::class))]
            public function owner(int|float $value): Value {
                return Value::from(null);
            }
        };
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Unregistered PHP type: ".Player::class);
        (new LanguageBuilder())->addExtension($extension)->build();
    }
}
