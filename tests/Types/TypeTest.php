<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Types;

use Mineflow\Language\ListValue;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\ListType;
use Mineflow\Language\Types\MapType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Value;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase {
    public function testACollectionOfMixedIsTheBareCollection(): void {
        self::assertSame("list", (string)Type::list(Type::mixed()));
        self::assertSame("map", (string)Type::map(Type::mixed()));
        self::assertSame("map<string,mixed>", (string)Type::map(Type::mixed(), Type::string()));

        $list = Builtin::List->type();
        $map = Builtin::Map->type();
        self::assertInstanceOf(ListType::class, $list);
        self::assertInstanceOf(MapType::class, $map);
        self::assertSame("mixed", (string)$list->element);
        self::assertSame("number|string", (string)$map->key);
    }

    public function testACollectionTypeAcceptsOnlyCollectionsWhoseKeysAndItemsItAccepts(): void {
        $type = Type::map(Type::list(Type::union(Type::number(), Type::null())));

        self::assertSame("map<number|string,list<null|number>>", (string)$type);
        self::assertTrue($type->accepts(Value::from(["scores" => [2, null, 4]])->type));
        self::assertFalse($type->accepts(Value::from(["scores" => [2, "four"]])->type));
        self::assertTrue(Type::list(Type::string())->accepts((new ListValue([]))->type));
        self::assertTrue(Type::map(Type::string())->accepts(Type::map(Type::string(), Type::string())));
        self::assertFalse(Type::map(Type::string(), Type::string())->accepts(Type::map(Type::string())));
        self::assertFalse(Type::list(Type::string())->accepts(Type::list(Type::mixed())));
    }

    public function testTypesAreComparedByWhatTheyAre(): void {
        self::assertTrue(Type::union(Type::string(), Type::number())->equals(Type::union(Type::number(), Type::string())));
        self::assertInstanceOf(ListType::class, Type::union(Type::list(Type::number()), Type::list(Type::number())));
        self::assertFalse(Type::list(Type::string())->equals(Type::list(Type::number())));
        self::assertTrue(Type::object("thing", self::class)->equals(Type::object("thing", self::class)));
        self::assertFalse(Type::object("thing", self::class)->equals(Type::object("thing", TestCase::class)));
        self::assertTrue(Type::map(Type::mixed())->equals(Type::map(Type::mixed(), Type::union(Type::number(), Type::string()))));
        self::assertFalse(Type::union(Type::string(), Type::null())->equals(Type::union(Type::string(), Type::number())));
    }
}
