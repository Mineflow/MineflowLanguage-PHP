<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Runtime\ValueMapper;
use Mineflow\Language\Types\ObjectTypes;
use Mineflow\Language\Types\Type;
use PHPUnit\Framework\TestCase;

class Weapon {
}

final class Sword extends Weapon {
}

final class ValueMapperTest extends TestCase {
    private ValueMapper $mapper;

    protected function setUp(): void {
        $this->mapper = new ValueMapper(new ObjectTypes(Type::object("weapon", Weapon::class)));
    }

    public function testAPlainArrayIsReadByItsOwnKeys(): void {
        self::assertSame("list<number>", (string)$this->mapper->wrap([1, 2])->type);
        self::assertSame("map<string,number>", (string)$this->mapper->wrap(["a" => 1])->type);
    }

    public function testAnEmptyArrayTakesTheShapeTheDeclarationAskedFor(): void {
        $map = Type::map(Type::string(), Type::string());

        self::assertSame("map<never,never>", (string)$this->mapper->wrap([], $map)->type);
        self::assertSame("list<never>", (string)$this->mapper->wrap([], Type::list(Type::string()))->type);
        // Without a declaration, an empty array becomes a list, as PHP treats it.
        self::assertSame("list<never>", (string)$this->mapper->wrap([])->type);
    }

    public function testADeclarationOfOneCollectionInsideAUnionStillDecides(): void {
        $expected = Type::union(Type::list(Type::number()), Type::string());

        self::assertSame("list<number>", (string)$this->mapper->wrap([1], $expected)->type);
    }

    public function testAUnionNamingBothCollectionsLeavesTheArrayToDecide(): void {
        $expected = Type::union(Type::list(Type::number()), Type::map(Type::number(), Type::string()));

        self::assertSame("list<number>", (string)$this->mapper->wrap([1], $expected)->type);
        self::assertSame("map<string,number>", (string)$this->mapper->wrap(["a" => 1], $expected)->type);
    }

    public function testTheDeclaredElementTypeReachesEveryItem(): void {
        $expected = Type::map(Type::list(Type::number()), Type::string());

        self::assertSame("map<string,list<number>>", (string)$this->mapper->wrap(["a" => [1, 2]], $expected)->type);
    }

    public function testAnAssociativeArrayWhereAListWasDeclaredIsRefused(): void {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("Expected a PHP list, got an associative array.");

        $this->mapper->wrap(["a" => 1], Type::list(Type::string()));
    }

    public function testASubclassIsWrappedAsTheRegisteredParent(): void {
        self::assertSame("weapon", (string)$this->mapper->wrap(new Sword())->type);
    }

    public function testAPhpValueWithNoLanguageCounterpartIsRefused(): void {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("No language value for a PHP resource (stream)");

        $this->mapper->wrap([fopen("php://memory", "r")]);
    }

    public function testAnUnregisteredObjectNamesItsClass(): void {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("Unregistered object: ".ValueMapperTest::class);

        $this->mapper->wrap($this);
    }
}
