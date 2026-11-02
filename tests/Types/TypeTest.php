<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Types;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\PlainType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\TypeDeclarations;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

#[DeclareType("thing")]
readonly class ThingValue extends Value {
    public function __toString(): string {
        return "thing";
    }
}

#[DeclareType("other_thing")]
final readonly class OtherThingValue extends Value {
    public function __toString(): string {
        return "other thing";
    }
}

final class TypeTest extends TestCase {
    public function testACollectionWritesTheTypeArgumentsItsDeclarationLeavesUndecided(): void {
        self::assertSame("list", (string)StandardTypes::list(Type::mixed()));
        self::assertSame("map", (string)StandardTypes::map(Type::mixed()));
        self::assertSame("collection", (string)TypeDeclarations::of(CollectionValue::class));
        self::assertSame("map<string,mixed>", (string)StandardTypes::map(Type::mixed(), StandardTypes::string()));
        self::assertSame("list<string>", (string)StandardTypes::list(StandardTypes::string()));

        $list = TypeDeclarations::of(ListValue::class);
        $map = TypeDeclarations::of(MapValue::class);
        self::assertInstanceOf(CollectionType::class, $list);
        self::assertInstanceOf(CollectionType::class, $map);
        self::assertSame("number", (string)$list->key);
        self::assertSame("mixed", (string)$list->element);
        self::assertSame("number|string", (string)$map->key);
        self::assertSame([], TypeDeclarations::undecided(StringValue::class));
        self::assertInstanceOf(PlainType::class, TypeDeclarations::of(StringValue::class));
    }

    public function testACollectionTypeAcceptsACollectionOfItsClassWhateverItHolds(): void {
        $type = StandardTypes::map(StandardTypes::list(Type::union(StandardTypes::number(), StandardTypes::null())));

        self::assertSame("map<number|string,list<null|number>>", (string)$type);
        self::assertSame("map", (string)(new MapValue(["scores" => new ListValue([new NumberValue(2), new StringValue("four")])]))->type());
        self::assertTrue($type->accepts((new MapValue(["scores" => new ListValue([new NumberValue(2), new NullValue(), new NumberValue(4)])]))->type()));
        self::assertTrue($type->accepts((new MapValue(["scores" => new ListValue([new NumberValue(2), new StringValue("four")])]))->type()));
        self::assertTrue(StandardTypes::list(StandardTypes::string())->accepts((new ListValue([new NumberValue(1)]))->type()));
        self::assertTrue(StandardTypes::map(StandardTypes::string(), StandardTypes::string())->accepts(StandardTypes::map(StandardTypes::number())));
        self::assertTrue(TypeDeclarations::of(CollectionValue::class)->accepts(StandardTypes::list(StandardTypes::string())));
        self::assertFalse(StandardTypes::list(Type::mixed())->accepts(StandardTypes::map(Type::mixed())));
        self::assertFalse(StandardTypes::list(StandardTypes::string())->accepts(StandardTypes::string()));
    }

    public function testTypesAreComparedByWhatTheyAre(): void {
        self::assertTrue(Type::union(StandardTypes::string(), StandardTypes::number())->equals(Type::union(StandardTypes::number(), StandardTypes::string())));
        self::assertInstanceOf(CollectionType::class, Type::union(StandardTypes::list(StandardTypes::number()), StandardTypes::list(StandardTypes::number())));
        self::assertFalse(StandardTypes::list(StandardTypes::string())->equals(StandardTypes::list(StandardTypes::number())));
        self::assertTrue(TypeDeclarations::of(ThingValue::class)->equals(TypeDeclarations::of(ThingValue::class)));
        self::assertFalse(TypeDeclarations::of(ThingValue::class)->equals(TypeDeclarations::of(OtherThingValue::class)));
        self::assertTrue(StandardTypes::map(Type::mixed())->equals(StandardTypes::map(Type::mixed(), Type::union(StandardTypes::number(), StandardTypes::string()))));
        self::assertFalse(Type::union(StandardTypes::string(), StandardTypes::null())->equals(Type::union(StandardTypes::string(), StandardTypes::number())));
    }
}
