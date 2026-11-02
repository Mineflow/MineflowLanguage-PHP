<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeRegistry;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\Value;
use Mineflow\Language\Values\ValueMapper;
use PHPUnit\Framework\TestCase;

#[DeclareType("weapon")]
readonly class WeaponValue extends Value {
    public function __toString(): string {
        return "weapon";
    }
}

final readonly class SwordValue extends WeaponValue {
}

#[DeclareType("shield")]
final readonly class ShieldValue extends Value {
    public function __toString(): string {
        return "shield";
    }
}

final class ValueMapperTest extends TestCase {
    private ValueMapper $mapper;

    protected function setUp(): void {
        $this->mapper = new ValueMapper(new TypeRegistry([...StandardTypes::CLASSES, WeaponValue::class]));
    }

    public function testAPlainArrayIsReadByItsOwnKeys(): void {
        self::assertSame("list", (string)$this->mapper->wrap([1, 2])->type());
        self::assertSame("map", (string)$this->mapper->wrap(["a" => 1])->type());
    }

    public function testAnEmptyArrayTakesTheShapeTheDeclarationAskedFor(): void {
        $map = StandardTypes::map(StandardTypes::string(), StandardTypes::string());

        self::assertSame("map<number|string,string>", (string)$this->mapper->wrap([], $map)->type());
        self::assertSame("list<string>", (string)$this->mapper->wrap([], StandardTypes::list(StandardTypes::string()))->type());
        // Without a declaration, an empty array becomes a list, as PHP treats it.
        self::assertSame("list", (string)$this->mapper->wrap([])->type());
    }

    public function testADeclarationOfOneCollectionInsideAUnionStillDecides(): void {
        $expected = Type::union(StandardTypes::list(StandardTypes::number()), StandardTypes::string());

        self::assertSame("map<number|string,number>", (string)$this->mapper->wrap([1], Type::union(StandardTypes::map(StandardTypes::number()), StandardTypes::string()))->type());
        self::assertSame("list<number>", (string)$this->mapper->wrap([1], $expected)->type());
    }

    public function testAUnionNamingBothCollectionsLeavesTheArrayToDecide(): void {
        $expected = Type::union(StandardTypes::list(StandardTypes::number()), StandardTypes::map(StandardTypes::number(), StandardTypes::string()));

        self::assertSame("list", (string)$this->mapper->wrap([1], $expected)->type());
        self::assertSame("map", (string)$this->mapper->wrap(["a" => 1], $expected)->type());
    }

    public function testTheDeclaredElementTypeReachesEveryItem(): void {
        $expected = StandardTypes::map(StandardTypes::map(StandardTypes::number()), StandardTypes::string());
        $wrapped = $this->mapper->wrap(["a" => [1, 2]], $expected);

        self::assertInstanceOf(MapValue::class, $wrapped);
        self::assertInstanceOf(MapValue::class, $wrapped->item("a"));
    }

    public function testAnAssociativeArrayWhereAListWasDeclaredIsRefused(): void {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("Expected a PHP list, got an associative array.");

        $this->mapper->wrap(["a" => 1], StandardTypes::list(StandardTypes::string()));
    }

    public function testAValueOfARegisteredTypeIsTakenAsItIs(): void {
        $sword = new SwordValue();

        self::assertSame($sword, $this->mapper->wrap($sword));
        self::assertSame([$sword], $this->mapper->wrap([$sword])->native());
    }

    public function testAValueOfAnUnregisteredTypeIsRefusedUnlessTheMapperTakesAnyType(): void {
        self::assertSame("shield", (string)(new ValueMapper())->wrap(new ShieldValue())->type());

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("Unregistered type: shield");
        $this->mapper->wrap(["s" => new ShieldValue()]);
    }

    public function testAPhpValueWithNoLanguageCounterpartIsRefused(): void {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("No language value for a PHP resource (stream)");

        $this->mapper->wrap([fopen("php://memory", "r")]);
    }

    public function testAPhpObjectNamesItsClass(): void {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("No language value for a PHP object of ".ValueMapperTest::class."; pass a Value of its type");

        $this->mapper->wrap($this);
    }
}
