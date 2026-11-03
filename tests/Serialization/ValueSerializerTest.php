<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Serialization;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\StandardLibrary\Codecs\StringCodec;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Values\BooleanValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[DeclareType("point")]
final readonly class PointValue extends Value {
    public function __construct(public int $x, public int $y, public string $world) {
    }

    public function __toString(): string {
        return "($this->x,$this->y)";
    }
}

/**
 * Holds what only the host knows, as a host's codec would hold its server.
 * @implements ValueCodec<PointValue>
 */
final readonly class PointCodec implements ValueCodec {
    /**
     * @param list<string> $worlds
     */
    public function __construct(private array $worlds) {
    }

    public function valueClass(): string {
        return PointValue::class;
    }

    /**
     * @return array{x: int, y: int, world: string}
     */
    public function encode(Value $value, ValueSerializer $serializer): array {
        return ["x" => $value->x, "y" => $value->y, "world" => $value->world];
    }

    public function decode(mixed $data, ValueSerializer $serializer): PointValue {
        if (!is_array($data) || !is_int($data["x"] ?? null) || !is_int($data["y"] ?? null) || !in_array($data["world"] ?? null, $this->worlds, true)) {
            throw new InvalidValue("Not a saved point: ".json_encode($data));
        }

        return new PointValue($data["x"], $data["y"], $data["world"]);
    }
}

#[DeclareType("path", typeHint: new ListOf(PointValue::class))]
final readonly class PathValue extends ListValue {
}

#[DeclareType("secret")]
final readonly class SecretValue extends Value {
    public function __toString(): string {
        return "***";
    }
}

#[DeclareType("container")]
interface ContainerValue {
}

#[DeclareType("chest")]
interface ChestValue extends ContainerValue {
}

#[DeclareType("crate")]
readonly class CrateValue extends Value implements ContainerValue {
    public function __toString(): string {
        return "crate";
    }
}

#[DeclareType("locked_crate")]
final readonly class LockedCrateValue extends CrateValue implements ChestValue {
}

/**
 * @implements ValueCodec<ContainerValue>
 */
final readonly class ContainerCodec implements ValueCodec {
    /**
     * @param class-string<ContainerValue> $interface
     */
    public function __construct(private string $interface) {
    }

    public function valueClass(): string {
        return $this->interface;
    }

    public function encode(Value $value, ValueSerializer $serializer): null {
        return null;
    }

    public function decode(mixed $data, ValueSerializer $serializer): LockedCrateValue {
        return new LockedCrateValue();
    }
}

/**
 * @implements ValueCodec<Value>
 */
final readonly class BrokenPointCodec implements ValueCodec {
    public function valueClass(): string {
        return PointValue::class;
    }

    public function encode(Value $value, ValueSerializer $serializer): null {
        return null;
    }

    public function decode(mixed $data, ValueSerializer $serializer): Value {
        return new StringValue("not a point");
    }
}

final class ValueSerializerTest extends TestCase {
    private Language $language;

    protected function setUp(): void {
        $this->language = self::builder()->build();
    }

    private static function builder(): LanguageBuilder {
        return (new LanguageBuilder())
            ->addType(PointValue::class)
            ->addType(PathValue::class)
            ->addType(SecretValue::class)
            ->addCodec(new PointCodec(["world", "nether"]));
    }

    /**
     * @return iterable<string, array{Value, array{type: string, value: mixed}}>
     */
    public static function standardValues(): iterable {
        yield "string" => [new StringValue("abc"), ["type" => "string", "value" => "abc"]];
        yield "int" => [new NumberValue(3), ["type" => "number", "value" => 3]];
        yield "float" => [new NumberValue(3.0), ["type" => "number", "value" => 3.0]];
        yield "boolean" => [new BooleanValue(false), ["type" => "boolean", "value" => false]];
        yield "null" => [new NullValue(), ["type" => "null", "value" => null]];
        yield "empty list" => [new ListValue(), ["type" => "list", "value" => []]];
        yield "empty map" => [new MapValue(), ["type" => "map", "value" => []]];
        yield "list" => [
            new ListValue([new StringValue("a"), new NumberValue(1)]),
            ["type" => "list", "value" => [["type" => "string", "value" => "a"], ["type" => "number", "value" => 1]]],
        ];
        yield "map" => [
            new MapValue(["a" => new ListValue([new NullValue()]), 5 => new MapValue()]),
            ["type" => "map", "value" => [
                "a" => ["type" => "list", "value" => [["type" => "null", "value" => null]]],
                5 => ["type" => "map", "value" => []],
            ]],
        ];
    }

    /**
     * @param array{type: string, value: mixed} $data
     */
    #[DataProvider("standardValues")]
    public function testAStandardValueIsWrittenWithItsTypeAndReadBack(Value $value, array $data): void {
        $serialized = $this->language->serializer->serialize($value);

        self::assertSame($data, $serialized);
        self::assertEquals($value, $this->language->serializer->deserialize($serialized));
    }

    /**
     * @param array{type: string, value: mixed} $data
     */
    #[DataProvider("standardValues")]
    public function testAValueKeepsItsTypeThroughJson(Value $value, array $data): void {
        $json = json_encode($this->language->serializer->serialize($value), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true);

        self::assertSame($data, $decoded);
        self::assertEquals($value, $this->language->serializer->deserialize($decoded));
    }

    public function testAMapWithTheKeysOfAListIsStillReadAsAMap(): void {
        $map = new MapValue([new StringValue("a"), new StringValue("b")]);
        $json = json_encode($this->language->serializer->serialize($map), JSON_THROW_ON_ERROR);

        self::assertSame('{"type":"map","value":[{"type":"string","value":"a"},{"type":"string","value":"b"}]}', $json);
        self::assertEquals($map, $this->language->serializer->deserialize(json_decode($json, true)));
    }

    public function testANumberWrittenAsAStringIsRead(): void {
        $read = $this->language->serializer->deserialize(["type" => "number", "value" => "12345678901234567890"]);

        self::assertInstanceOf(NumberValue::class, $read);
        self::assertSame(1.2345678901234567E19, $read->value);
    }

    public function testTheElementHintIsNotWritten(): void {
        $list = new ListValue([new NumberValue(1)], StandardTypes::number());

        self::assertSame("list<number>", (string)$list->type());
        self::assertSame("list", (string)$this->language->serializer->deserialize($this->language->serializer->serialize($list))->type());
    }

    public function testAHostTypeIsWrittenByTheCodecTheHostAdded(): void {
        $list = new ListValue([new PointValue(1, 2, "nether")]);
        $serialized = $this->language->serializer->serialize($list);

        self::assertSame(["type" => "list", "value" => [["type" => "point", "value" => ["x" => 1, "y" => 2, "world" => "nether"]]]], $serialized);
        self::assertEquals($list, $this->language->serializer->deserialize($serialized));
    }

    public function testACodecRefusesDataOfAnotherShape(): void {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Not a saved point: {"x":1,"y":2,"world":"end"}');

        $this->language->serializer->deserialize(["type" => "point", "value" => ["x" => 1, "y" => 2, "world" => "end"]]);
    }

    public function testASubtypeWithoutACodecIsWrittenAsItsNearestParentWithOne(): void {
        $path = new PathValue([new PointValue(0, 0, "world")]);
        $read = $this->language->serializer->deserialize($this->language->serializer->serialize($path));

        self::assertSame("list", $this->language->serializer->serialize($path)["type"]);
        self::assertEquals(new ListValue($path->items()), $read);
    }

    public function testTheCodecOfTheMostSpecificInterfaceWritesAValue(): void {
        $language = (new LanguageBuilder())
            ->addType(ContainerValue::class)
            ->addType(ChestValue::class)
            ->addType(CrateValue::class)
            ->addType(LockedCrateValue::class)
            ->addCodec(new ContainerCodec(ContainerValue::class))
            ->addCodec(new ContainerCodec(ChestValue::class))
            ->build();

        self::assertSame("chest", $language->serializer->serialize(new LockedCrateValue())["type"]);
        self::assertSame("container", $language->serializer->serialize(new CrateValue())["type"]);
    }

    public function testAValueWithoutACodecIsRefused(): void {
        self::assertFalse($this->language->serializer->hasCodecFor(new SecretValue()));
        self::assertTrue($this->language->serializer->hasCodecFor(new PathValue()));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("No codec writes a value of the type secret");

        $this->language->serializer->serialize(new ListValue([new SecretValue()]));
    }

    public function testTheFallbackWritesAValueWithoutACodecAtAnyDepth(): void {
        $serializer = self::builder()->setSerializationFallback(static fn(Value $value) => new StringValue((string)$value))->build()->serializer;

        self::assertSame(
            ["type" => "list", "value" => [["type" => "string", "value" => "***"]]],
            $serializer->serialize(new ListValue([new SecretValue()])),
        );
        self::assertFalse($serializer->hasCodecFor(new SecretValue()));
    }

    public function testTheFallbackHasToGiveAValueACodecWrites(): void {
        $serializer = self::builder()->setSerializationFallback(static fn(Value $value) => $value)->build()->serializer;

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("The fallback gave a value of the type secret, which no codec writes either");

        $serializer->serialize(new SecretValue());
    }

    public function testATypeIsReadUnderItsAliases(): void {
        $serializer = self::builder()
            ->addDeserializationAliases([0 => "string", 1 => "number", 2 => "list"])
            ->addDeserializationAliases([3 => "map", 5 => "boolean", 6 => "null"])
            ->build()->serializer;
        $saved = json_decode('{"type": 3, "value": {"a": {"type": 0, "value": "x"}, "b": {"type": 2, "value": [{"type": 1, "value": 2}, {"type": 6, "value": null}, {"type": 5, "value": true}]}}}', true);

        self::assertEquals(
            new MapValue(["a" => new StringValue("x"), "b" => new ListValue([new NumberValue(2), new NullValue(), new BooleanValue(true)])]),
            $serializer->deserialize($saved),
        );
        self::assertSame("string", $serializer->serialize(new StringValue("x"))["type"]);
    }

    /**
     * @return iterable<string, array{array<int|string, string>, string}>
     */
    public static function badAliases(): iterable {
        yield "the name of a type" => [["list" => "map"], 'The alias "list" is already the name of a type'];
        yield "an unknown type" => [["0" => "text"], 'The alias "0" stands for an unknown type: text'];
        yield "a type without a codec" => [["9" => "secret"], 'The alias "9" stands for secret, which has no codec'];
    }

    /**
     * @param array<int|string, string> $aliases
     */
    #[DataProvider("badAliases")]
    public function testAnAliasHasToStandForATypeWithACodec(array $aliases, string $message): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage($message);

        self::builder()->addDeserializationAliases($aliases)->build();
    }

    public function testAnAliasIsGivenOnce(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage('The alias "0" already stands for string');

        self::builder()->addDeserializationAliases([0 => "string"])->addDeserializationAliases([0 => "number"]);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badData(): iterable {
        yield "not an array" => ["abc", 'A saved value is an array of "type" and "value", not "abc"'];
        yield "no value" => [["type" => "string"], 'A saved value is an array of "type" and "value", not {"type":"string"}'];
        yield "a type that is not a name" => [["type" => [], "value" => 1], "The type of a saved value is a name, not []"];
        yield "an unknown type" => [["type" => "text", "value" => "a"], "Unknown type of a saved value: text"];
        yield "a type without a codec" => [["type" => "secret", "value" => null], "No codec reads a value of the type secret"];
        yield "a number alias without aliases" => [["type" => 0, "value" => "a"], "Unknown type of a saved value: 0"];
        yield "a string of another kind" => [["type" => "string", "value" => 1], "A saved string is a string, not 1"];
        yield "a number of another kind" => [["type" => "number", "value" => "one"], 'A saved number is a number, not "one"'];
        yield "a boolean of another kind" => [["type" => "boolean", "value" => 1], "A saved boolean is true or false, not 1"];
        yield "a null of another kind" => [["type" => "null", "value" => 0], "A saved null is null, not 0"];
        yield "a list of another kind" => [["type" => "list", "value" => "a"], 'A saved list is an array of its items, not "a"'];
        yield "a map of another kind" => [["type" => "map", "value" => 1], "A saved map is an array of its items under their keys, not 1"];
        yield "an item of another kind" => [["type" => "list", "value" => [1]], 'A saved value is an array of "type" and "value", not 1'];
    }

    #[DataProvider("badData")]
    public function testDataOfAnotherShapeIsRefused(mixed $data, string $message): void {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage($message);

        $this->language->serializer->deserialize($data);
    }

    public function testACodecHasToMakeAValueOfItsOwnType(): void {
        $language = (new LanguageBuilder())->addType(PointValue::class)->addCodec(new BrokenPointCodec())->build();

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage(BrokenPointCodec::class." made a value of the type string for one of point");

        $language->serializer->deserialize(["type" => "point", "value" => null]);
    }

    public function testACodecForATypeTheLanguageDoesNotHaveIsRefused(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("A codec for ".PointValue::class.", whose type the language does not have");

        (new LanguageBuilder())->addCodec(new PointCodec([]))->build();
    }

    public function testTwoCodecsForOneTypeAreRefused(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Both ".PointCodec::class." and ".BrokenPointCodec::class." write the values of point");

        (new LanguageBuilder())->addType(PointValue::class)->addCodec(new PointCodec([]))->addCodec(new BrokenPointCodec())->build();
    }

    public function testAStandardTypeAlreadyHasItsCodec(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("write the values of string");

        (new LanguageBuilder())->addCodec(new StringCodec())->build();
    }
}
