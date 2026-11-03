<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Types;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\FromNative;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\Expression\CollectionOf;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Values\BooleanValue;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\TypeDeclarations;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

final class Item {
    public function __construct(public string $label) {
    }
}

#[DeclareType("item")]
final readonly class ItemValue extends Value {
    public function __construct(public Item $item) {
    }

    #[Property]
    public function label(): string {
        return $this->item->label;
    }

    public function __toString(): string {
        return "item";
    }
}

#[ExtendType(ItemValue::class)]
final class ItemExtras {
    #[Method]
    public function shout(ItemValue $item): string {
        return strtoupper($item->item->label)."!";
    }
}

#[DeclareType]
final readonly class UnnamedValue extends Value {
    public function __toString(): string {
        return "unnamed";
    }
}

#[DeclareType("string")]
final readonly class BuiltinNameValue extends Value {
    public function __toString(): string {
        return "builtin name";
    }
}

#[DeclareType("item")]
final readonly class SameNameValue extends Value {
    public function __toString(): string {
        return "same name";
    }
}

#[DeclareType("mixed")]
final readonly class SpecialNameValue extends Value {
    public function __toString(): string {
        return "special name";
    }
}

#[DeclareType("list<number>")]
final readonly class NotAWordValue extends Value {
    public function __toString(): string {
        return "not a word";
    }
}

final readonly class UndeclaredValue extends Value {
    public function __toString(): string {
        return "undeclared";
    }
}

#[DeclareType("plain")]
final class NotAValue {
}

#[DeclareType("labels", typeHint: new ListOf(StringValue::class))]
readonly class LabelsValue extends ListValue {
}

#[DeclareType("names", typeHint: new ListOf(Special::Mixed))]
final readonly class WiderLabelsValue extends LabelsValue {
}

#[DeclareType("same_labels", typeHint: new ListOf(StringValue::class))]
final readonly class SameLabelsValue extends LabelsValue {
}

#[DeclareType("any_list", typeHint: new ListOf(Special::Mixed))]
final readonly class AnyListValue extends ListValue {
}

#[DeclareType("tags", typeHint: new MapOf(StringValue::class))]
final readonly class MapForAListValue extends ListValue {
}

#[DeclareType("thing", typeHint: new ListOf(StringValue::class))]
final readonly class CollectionOfAPlainValue extends Value {
    public function __toString(): string {
        return "collection of a plain";
    }
}

#[DeclareType("tree", typeHint: new MapOf(TreeValue::class))]
final readonly class TreeValue extends MapValue {
}

/**
 * A collection that reads its items afresh, without a typeHint: its type is worked out from them each time.
 */
#[DeclareType("live")]
final readonly class LiveValue extends CollectionValue {
    public function __construct(private Shelf $shelf) {
    }

    public function items(): array {
        return $this->shelf->items;
    }

    public function __toString(): string {
        return "live";
    }
}

final class Shelf {
    /** @var array<string, Value> */
    public array $items = [];
}

#[DeclareType("uuid")]
final readonly class UuidValue extends StringValue {
    #[Property]
    public function short(): string {
        return substr($this->value, 0, 4);
    }
}

#[DeclareType("count")]
final readonly class CountValue extends NumberValue {
}

#[DeclareType("flag")]
final readonly class FlagValue extends BooleanValue {
}

#[DeclareType("nothing")]
final readonly class NothingValue extends NullValue {
}

#[DeclareType("code", typeHint: new CollectionOf(NumberValue::class))]
final readonly class CollectionForAStringValue extends StringValue {
}

#[DeclareType("converted")]
final readonly class ConvertedItemValue extends Value {
    public function __construct(public Item $item) {
    }

    #[FromNative]
    public static function fromItem(Item $item, string $unused = ""): static {
        return new static($item);
    }

    public function __toString(): string {
        return "converted item";
    }
}

#[DeclareType("other_converted")]
final readonly class OtherConvertedItemValue extends Value {
    #[FromNative]
    public static function fromItem(Item $item): self {
        return new self();
    }

    public function __toString(): string {
        return "other converted item";
    }
}

#[DeclareType("wrong_return")]
final readonly class WrongReturnValue extends Value {
    #[FromNative]
    public static function fromItem(Item $item): ItemValue {
        return new ItemValue($item);
    }

    public function __toString(): string {
        return "wrong return";
    }
}

#[DeclareType("array_converter")]
final readonly class ArrayConverterValue extends Value {
    /**
     * @param array<mixed> $items
     */
    #[FromNative]
    public static function fromArray(array $items): self {
        return new self();
    }

    public function __toString(): string {
        return "array converter";
    }
}

final class BuiltinSubtypeFunctions {
    #[Method]
    public function quote(string $text): string {
        return "\"$text\"";
    }

    #[Method]
    public function twice(int|float $number): int|float {
        return $number * 2;
    }

    #[Method]
    public function either(bool $a, bool $b): bool {
        return $a || $b;
    }

    #[Method]
    public function missing(null $value): string {
        return "none";
    }
}

/**
 * Declaring a type on a Value class with #[DeclareType], and the declarations that are refused.
 */
final class ObjectTypeDeclarationTest extends TestCase {
    public function testATypeHasTheMembersItsClassAndItsExtensionsDeclare(): void {
        $language = (new LanguageBuilder())->addType(ItemValue::class)->addExtension(new ItemExtras())->build();
        $scope = $language->scope(["item" => new ItemValue(new Item("sword"))]);

        self::assertSame("item", (string)$language->evaluate("item", $scope)->type());
        self::assertSame("sword", $language->evaluate("item.label", $scope)->native());
        self::assertSame("SWORD!", $language->evaluate("item.shout()", $scope)->native());
    }

    public function testATypeWithoutANameIsNamedAfterItsClass(): void {
        self::assertSame("UnnamedValue", (string)(new UnnamedValue())->type());
    }

    public function testAValueOfATypeTheLanguageDoesNotHaveIsRefused(): void {
        $language = (new LanguageBuilder())->build();

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("Unregistered type: item");
        $language->scope(["item" => new ItemValue(new Item("sword"))]);
    }

    public function testAPhpObjectWithoutAConversionIsNotAValue(): void {
        $language = (new LanguageBuilder())->addType(ItemValue::class)->build();

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("No language value for a PHP object of ".Item::class."; pass a Value of its type, or declare #[FromNative] for it");
        $language->scope(["item" => new Item("sword")]);
    }

    public function testAPhpObjectBecomesTheValueOfTheTypeThatConvertsIt(): void {
        $language = (new LanguageBuilder())->addType(ConvertedItemValue::class)->build();
        $value = $language->scope(["item" => new Item("sword")])->get("item");

        self::assertInstanceOf(ConvertedItemValue::class, $value);
        self::assertSame("sword", $value->item->label);
    }

    public function testAConversionIsAStaticMethodOfAPhpTypeIntoItsOwnClass(): void {
        $refusals = [
            OtherConvertedItemValue::class => "Both ".ConvertedItemValue::class."::fromItem() and ".OtherConvertedItemValue::class."::fromItem() convert the PHP type ".Item::class,
            WrongReturnValue::class => "A #[FromNative] method returns a value of its own class: ".WrongReturnValue::class."::fromItem()",
            ArrayConverterValue::class => "A #[FromNative] method converts PHP values of a class or of string, int, float, bool, null, not array: ".ArrayConverterValue::class."::fromArray()",
        ];
        foreach ($refusals as $class => $message) {
            try {
                (new LanguageBuilder())->addType(ConvertedItemValue::class)->addType(ItemValue::class)->addType($class)->build();

                self::fail("Expected $class to be refused.");
            } catch (DefinitionError $error) {
                self::assertSame($message, $error->getMessage(), $class);
            }
        }
    }

    public function testAValueClassWithoutADeclarationHasNoType(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Missing #[DeclareType] on ".UndeclaredValue::class." or its parent classes");

        (new UndeclaredValue())->type();
    }

    public function testATypeIsDeclaredOnAValueClassOrAnInterface(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("#[DeclareType] goes on a Value class or an interface: ".NotAValue::class);

        (new LanguageBuilder())->addType(NotAValue::class)->build();
    }

    public function testAnAddedClassNeedsItsOwnDeclaration(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Missing #[DeclareType] on ".UndeclaredValue::class);

        (new LanguageBuilder())->addType(UndeclaredValue::class)->build();
    }

    public function testEveryStandardTypeHasSubtypes(): void {
        $language = (new LanguageBuilder())
            ->addType(UuidValue::class)
            ->addType(CountValue::class)
            ->addType(FlagValue::class)
            ->addType(NothingValue::class)
            ->addFunctions(new BuiltinSubtypeFunctions())
            ->build();
        $scope = $language->scope(["id" => new UuidValue("1f0c-aa"), "n" => new CountValue(3), "f" => new FlagValue(true), "x" => new NothingValue(), "no" => false]);
        $cases = [
            "id" => "1f0c-aa", "id.short" => "1f0c", "id.uppercase" => "1F0C-AA", "id + 1" => "1f0c-aa1", "quote({id})" => "\"1f0c-aa\"",
            "n + 1" => 4.0, "1 + n" => 4.0, "n.abs" => 3, "twice({n})" => 6,
            "f + no" => true, "f * no" => false, "either({f}, {no})" => true,
            "missing({x})" => "none",
        ];

        foreach (["id" => StandardTypes::string(), "n" => StandardTypes::number(), "f" => StandardTypes::boolean(), "x" => StandardTypes::null()] as $name => $parent) {
            $type = $scope->get($name)->type();
            self::assertTrue($parent->accepts($type), $name);
            self::assertSame([$parent->name], array_map(static fn($ancestor) => $ancestor->name, $language->catalog->types->ancestors($type->name)));
        }
        foreach ($cases as $source => $expected) {
            self::assertSame($expected, $language->evaluate($source, $scope)->native(), $source);
        }
        self::assertSame("1f0c-aa / 3 / true / null", $language->render("{id} / {n} / {f} / {x}", $scope));
    }

    public function testATypeHintDecidesTheTypeArgumentsItsParentLeaves(): void {
        $labels = new LabelsValue([new StringValue("a")]);
        self::assertSame("labels", (string)$labels->type());
        self::assertSame("labels", (string)(new LabelsValue([]))->type());
        $declared = TypeDeclarations::of(LabelsValue::class);
        self::assertInstanceOf(CollectionType::class, $declared);
        self::assertSame("string", (string)$declared->element);
        self::assertTrue(StandardTypes::list(StandardTypes::number())->accepts($labels->type()));
        self::assertSame([], TypeDeclarations::undecided(LabelsValue::class));
        self::assertSame("any_list", (string)(new AnyListValue([new NumberValue(1)]))->type());
        self::assertSame([], TypeDeclarations::undecided(AnyListValue::class));
        self::assertTrue(TypeDeclarations::of(LabelsValue::class)->accepts((new SameLabelsValue([]))->type()));

        $refusals = [
            WiderLabelsValue::class => "The element type of labels is string, which does not accept mixed",
            MapForAListValue::class => "The typeHint of ".MapForAListValue::class." gives the type arguments of map, which its parent class's type list is not",
            CollectionForAStringValue::class => "typeHint gives the key and element types of a collection, and ".CollectionForAStringValue::class." holds no items",
            CollectionOfAPlainValue::class => "typeHint gives the key and element types of a collection, and ".CollectionOfAPlainValue::class." holds no items",
            TreeValue::class => "The typeHint of the type tree refers to the type itself",
        ];
        foreach ($refusals as $class => $message) {
            try {
                (new LanguageBuilder())->addType($class)->build();

                self::fail("Expected $class to be refused.");
            } catch (DefinitionError $error) {
                self::assertSame($message, $error->getMessage(), $class);
            }
        }
    }

    public function testATypeHintItsItemsDoNotFitRefusesNothing(): void {
        $language = (new LanguageBuilder())->addType(LabelsValue::class)->build();
        $scope = $language->scope(["labels" => new LabelsValue([new NumberValue(1)])]);

        self::assertSame(1, $language->evaluate("labels.first()", $scope)->native());
        self::assertSame(3.0, $language->evaluate("labels.first() + 2", $scope)->native());
    }

    public function testTheTypeStaysTheSameWhateverTheItems(): void {
        $shelf = new Shelf();
        $shelf->items = ["a" => new NumberValue(1)];
        $live = new LiveValue($shelf);
        $before = (string)$live->type();

        $shelf->items["b"] = new StringValue("x");
        self::assertSame("live", $before);
        self::assertSame("live", (string)$live->type());
    }

    public function testAClassCannotBeAddedTwice(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate type: item is already declared for ".ItemValue::class);

        (new LanguageBuilder())->addType(ItemValue::class)->addType(ItemValue::class)->build();
    }

    public function testAStandardClassCannotBeAddedAgain(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate type: string is already declared for ".StringValue::class);

        (new LanguageBuilder())->addType(StringValue::class)->build();
    }

    public function testAStandardTypeNameIsTakenLikeAnyOther(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate type: string is already declared for ".StringValue::class
            .". Use #[ExtendType(".StringValue::class."::class)] to add members to it, or give this type another name.");

        (new LanguageBuilder())->addType(BuiltinNameValue::class)->build();
    }

    public function testRedeclaringATypePointsAtExtendTypeWithItsClass(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate type: item is already declared for ".ItemValue::class
            .". Use #[ExtendType(".ItemValue::class."::class)] to add members to it, or give this type another name.");

        (new LanguageBuilder())->addType(ItemValue::class)->addType(SameNameValue::class)->build();
    }

    public function testATypeCannotTakeTheNameOfASpecialType(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage('Reserved type name: "mixed"');

        (new LanguageBuilder())->addType(SpecialNameValue::class)->build();
    }

    public function testATypeNameMustBeAWord(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage('A type name is a word of letters, digits and _, not starting with a digit: "list<number>"');

        (new LanguageBuilder())->addType(NotAWordValue::class)->build();
    }

    public function testAnExtensionOfAnUnregisteredClassIsRejected(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Unregistered extension target: ".ItemValue::class);

        (new LanguageBuilder())->addExtension(new ItemExtras())->build();
    }

    public function testAClassThatDoesNotExistIsRejected(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Unknown class for a type: Mineflow\Language\Tests\Types\NoSuchItem");

        // @phpstan-ignore argument.type
        (new LanguageBuilder())->addType("Mineflow\Language\Tests\Types\NoSuchItem")->build();
    }
}
