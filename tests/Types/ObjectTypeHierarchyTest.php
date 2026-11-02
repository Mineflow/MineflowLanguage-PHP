<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Types;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Index;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Definition\MemberKind;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\ArgumentTypeMismatch;
use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[DeclareType("creature")]
readonly class CreatureValue extends Value {
    final public function __construct(public string $name, public int $health = 20) {
    }

    #[Property]
    public function name(): string {
        return $this->name;
    }

    #[Property]
    public function kind(): string {
        return "creature";
    }

    #[Property]
    public function sound(): string {
        return "...";
    }

    #[Method(type: Placeholder::Target, description: "The same creature.")]
    public function self(): CreatureValue {
        return $this;
    }

    #[Method]
    public function fail(): string {
        throw new RuntimeException("failed");
    }

    #[Operator(BinaryOperator::Add)]
    public function heal(int $by): CreatureValue {
        return new static($this->name, $this->health + $by);
    }

    #[Property]
    public function health(): int {
        return $this->health;
    }

    #[Operator(BinaryOperator::Multiply)]
    public function times(CreatureValue $other): string {
        return "creature";
    }

    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function timesFrom(CreatureValue $other): string {
        return "creature reflected";
    }

    public function __toString(): string {
        return "creature";
    }
}

#[DeclareType("animal")]
readonly class AnimalValue extends CreatureValue {
    #[Property]
    public function kind(): string {
        return "animal";
    }

    #[Property]
    public function sound(): string {
        return "growl";
    }
}

#[DeclareType("hero")]
final readonly class HeroValue extends AnimalValue {
    #[Property]
    public function kind(): string {
        return "hero";
    }

    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function timesFrom(CreatureValue $other): string {
        return "hero";
    }
}

/**
 * Has no #[DeclareType] of its own, so its values are of the type of its parent class.
 */
final readonly class SlimeValue extends CreatureValue {
}

#[ExtendType(CreatureValue::class)]
final class CreatureExtras {
    #[Method]
    public function shout(CreatureValue $creature): string {
        return strtoupper($creature->name)."!";
    }
}

#[DeclareType("holder")]
interface HolderValue {
    #[Property]
    public function kind(): string;

    #[Index]
    public function slot(string $slot): ?string;
}

#[DeclareType("bag")]
interface BagValue extends HolderValue {
    #[Property]
    public function size(): int;
}

#[DeclareType("backpack")]
final readonly class BackpackValue extends Value implements BagValue {
    /**
     * @param list<string> $items
     */
    public function __construct(private array $items) {
    }

    public function kind(): string {
        return "holder";
    }

    public function slot(string $slot): ?string {
        return $this->items[(int)$slot] ?? null;
    }

    public function size(): int {
        return count($this->items);
    }

    public function __toString(): string {
        return "backpack";
    }
}

#[DeclareType("pouch")]
final readonly class PouchValue extends Value implements BagValue {
    public function kind(): string {
        return "pouch";
    }

    public function slot(string $slot): ?string {
        return null;
    }

    public function size(): int {
        return 1;
    }

    public function __toString(): string {
        return "pouch";
    }
}

#[DeclareType("operand")]
interface OperandValue {
}

#[DeclareType("operator_base")]
readonly class OperatorBaseValue extends Value {
    #[Operator(BinaryOperator::Multiply)]
    public function times(OperatorBaseValue $other): string {
        return "base";
    }

    public function __toString(): string {
        return "operator base";
    }
}

#[ExtendType(OperandValue::class)]
final class OperandExtras {
    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function timesFrom(OperandValue $operand, OperatorBaseValue $other): string {
        return "operand reflected";
    }
}

#[DeclareType("operator_child")]
final readonly class OperatorChildValue extends OperatorBaseValue implements OperandValue {
}

#[DeclareType("tagged")]
interface TaggedValue {
    #[Property]
    public function label(): string;
}

/**
 * @extends \IteratorAggregate<int, string>
 */
#[DeclareType("sealed")]
interface SealedValue extends \Countable, \IteratorAggregate, \JsonSerializable {
    #[Property]
    public function label(): string;
}

#[DeclareType("parcel")]
final readonly class ParcelValue extends Value implements SealedValue, TaggedValue {
    public function label(): string {
        return "parcel";
    }

    public function count(): int {
        return 0;
    }

    /**
     * @return \ArrayIterator<int, string>
     */
    public function getIterator(): \ArrayIterator {
        return new \ArrayIterator([]);
    }

    public function jsonSerialize(): mixed {
        return null;
    }

    public function __toString(): string {
        return "parcel";
    }
}

final class HierarchyFunctions {
    #[Method]
    public function greet(CreatureValue $creature): string {
        return "hi ".$creature->name;
    }

    #[Method]
    public function pet(AnimalValue $animal): string {
        return "petted ".$animal->name;
    }

    #[Method]
    public function weigh(HolderValue $holder): string {
        return "weighed";
    }
}

final class ObjectTypeHierarchyTest extends TestCase {
    private Language $language;

    protected function setUp(): void {
        $this->language = (new LanguageBuilder())
            ->addType(CreatureValue::class)
            ->addType(AnimalValue::class)
            ->addType(HeroValue::class)
            ->addType(HolderValue::class)
            ->addType(BagValue::class)
            ->addType(BackpackValue::class)
            ->addExtension(new CreatureExtras())
            ->addFunctions(new HierarchyFunctions())
            ->build();
    }

    /**
     * @param array<string, mixed> $entries
     */
    private function evaluate(string $source, array $entries = []): mixed {
        return $this->language->evaluate($source, $this->language->scope($entries))->native();
    }

    public function testASubtypeHasTheMembersOfItsAncestors(): void {
        $hero = new HeroValue("aieuo");

        self::assertSame("aieuo", $this->evaluate("h.name", ["h" => $hero]));
        self::assertSame("AIEUO!", $this->evaluate("h.shout()", ["h" => $hero]));
    }

    public function testTheNearestDeclarationWins(): void {
        $hero = new HeroValue("aieuo");

        self::assertSame("hero", $this->evaluate("h.kind", ["h" => $hero]));
        self::assertSame("growl", $this->evaluate("h.sound", ["h" => $hero]));
        self::assertSame("...", $this->evaluate("c.sound", ["c" => new CreatureValue("slime")]));
    }

    public function testAnInheritedMemberIsCalledOnTheSubtype(): void {
        $scope = $this->language->scope(["h" => new HeroValue("aieuo")]);

        self::assertSame("hero", (string)$this->language->evaluate("h.self()", $scope)->type());
        self::assertSame("hero", (string)$this->language->evaluate("h + 5", $scope)->type());
        self::assertSame(25, $this->language->evaluate("(h + 5).health", $scope)->native());
    }

    public function testAFailureOfAnInheritedMemberNamesTheSubtype(): void {
        try {
            $this->evaluate("h.fail()", ["h" => new HeroValue("aieuo")]);

            self::fail("Expected an ExternalFailure.");
        } catch (ExternalFailure $error) {
            self::assertSame("hero.fail", (string)$error->reference);
        }
    }

    public function testASubtypeIsAcceptedWhereItsAncestorIsExpected(): void {
        self::assertSame("hi aieuo", $this->evaluate("greet({h})", ["h" => new HeroValue("aieuo")]));
        self::assertSame("petted aieuo", $this->evaluate("pet({h})", ["h" => new HeroValue("aieuo")]));
    }

    public function testAnAncestorIsNotAcceptedWhereItsSubtypeIsExpected(): void {
        $this->expectException(ArgumentTypeMismatch::class);
        $this->evaluate("pet({c})", ["c" => new CreatureValue("slime")]);
    }

    public function testAClassWithoutADeclarationOfItsOwnIsOfItsParentsType(): void {
        $scope = $this->language->scope(["s" => new SlimeValue("slime")]);

        self::assertSame("creature", (string)$this->language->evaluate("s", $scope)->type());
        self::assertSame("slime", $this->language->evaluate("s.name", $scope)->native());
    }

    public function testAClassHasTheMembersOfTheInterfacesItImplements(): void {
        $scope = $this->language->scope(["b" => new BackpackValue(["apple", "stone"])]);

        self::assertSame("backpack", (string)$this->language->evaluate("b", $scope)->type());
        self::assertSame(2, $this->language->evaluate("b.size", $scope)->native());
        self::assertSame("holder", $this->language->evaluate("b.kind", $scope)->native());
        self::assertSame("stone", $this->language->evaluate("b.1", $scope)->native());
        self::assertSame("weighed", $this->language->evaluate("weigh({b})", $scope)->native());
    }

    public function testASubtypeOnTheRightWithItsOwnReflectedOperatorIsAskedFirst(): void {
        $operands = ["c" => new CreatureValue("slime"), "a" => new AnimalValue("wolf"), "h" => new HeroValue("aieuo")];

        self::assertSame("hero", $this->evaluate("c * h", $operands));
        self::assertSame("hero", $this->evaluate("a * h", $operands));
        self::assertSame("creature", $this->evaluate("c * a", $operands));
        self::assertSame("creature", $this->evaluate("h * c", $operands));
        self::assertSame("creature", $this->evaluate("h * h", $operands));
    }

    public function testAReflectedOperatorFromAnAncestorThatIsNotASubtypeOfTheLeftTypeIsNotAskedFirst(): void {
        $language = (new LanguageBuilder())
            ->addType(OperandValue::class)
            ->addType(OperatorBaseValue::class)
            ->addType(OperatorChildValue::class)
            ->addExtension(new OperandExtras())
            ->build();

        $scope = $language->scope(["b" => new OperatorBaseValue(), "c" => new OperatorChildValue()]);
        self::assertSame("operand.*", (string)$language->catalog->origin(MemberReference::reflectedOperator("operator_child", BinaryOperator::Multiply)));
        self::assertSame("base", $language->evaluate("b * c", $scope)->native());
    }

    public function testTheCatalogListsWhatATypeHasAndWhatItDeclaresApart(): void {
        $catalog = $this->language->catalog;

        $properties = $catalog->members("hero")[MemberKind::Property->value];
        self::assertSame(["kind", "sound", "name", "health"], array_keys($properties));
        self::assertSame($catalog->definition(MemberReference::property("animal", "sound")), $properties["sound"]);
        self::assertSame(["kind"], array_keys($catalog->declaredMembers("hero")[MemberKind::Property->value]));
        self::assertNotNull($catalog->definition(MemberReference::index("bag")));
        self::assertNotNull($catalog->definition(MemberReference::method("hero", "shout")));
    }

    public function testAnInheritedMemberIsTracedToTheTypeThatDeclaresIt(): void {
        $catalog = $this->language->catalog;
        self::assertSame("creature.name", (string)$catalog->origin(MemberReference::property("hero", "name")));
        self::assertSame("animal.sound", (string)$catalog->origin(MemberReference::property("hero", "sound")));
        self::assertSame("hero.kind", (string)$catalog->origin(MemberReference::property("hero", "kind")));
        self::assertSame("holder.*", (string)$catalog->origin(MemberReference::index("bag")));
        self::assertSame("holder.kind", (string)$catalog->origin(MemberReference::property("backpack", "kind")));
        self::assertNull($catalog->origin(MemberReference::property("hero", "level")));
        self::assertSame("hero.name", (string)$catalog->declaration(MemberReference::property("hero", "name"))?->reference);
    }

    public function testTheSchemaHoldsEveryMemberOnceAndTheAncestorsOfEveryType(): void {
        $schema = $this->language->catalog->schema();
        self::assertSame(["kind"], array_keys($schema["types"]["hero"][MemberKind::Property->value]));
        self::assertArrayNotHasKey("backpack", $schema["types"]);
        self::assertSame([
            "list" => ["collection"],
            "map" => ["collection"],
            "animal" => ["creature"],
            "hero" => ["animal", "creature"],
            "bag" => ["holder"],
            "backpack" => ["bag", "holder"],
        ], $schema["ancestors"]);
    }

    public function testUnrelatedInterfacesAreInheritedInTheOrderTheyWereDeclared(): void {
        $label = MemberReference::property("parcel", "label");

        $language = (new LanguageBuilder())->addType(TaggedValue::class)->addType(SealedValue::class)->addType(ParcelValue::class)->build();
        self::assertSame(["tagged", "sealed"], array_map(strval(...), $language->catalog->types->ancestors("parcel")));
        self::assertSame("tagged.label", (string)$language->catalog->origin($label));
        self::assertSame("parcel", $language->evaluate("p.label", $language->scope(["p" => new ParcelValue()]))->native());

        $language = (new LanguageBuilder())->addType(SealedValue::class)->addType(TaggedValue::class)->addType(ParcelValue::class)->build();
        self::assertSame(["sealed", "tagged"], array_map(strval(...), $language->catalog->types->ancestors("parcel")));
        self::assertSame("sealed.label", (string)$language->catalog->origin($label));
    }

    public function testAnInterfaceComesBeforeOneItExtendsWhicheverWasDeclaredFirst(): void {
        foreach ([[HolderValue::class, BagValue::class], [BagValue::class, HolderValue::class]] as $interfaces) {
            $builder = new LanguageBuilder();

            foreach ([...$interfaces, PouchValue::class] as $type) {
                $builder->addType($type);
            }
            self::assertSame(["bag", "holder"], array_map(strval(...), $builder->build()->catalog->types->ancestors("pouch")));
        }
    }

    public function testAncestorsAreTheParentClassesThenTheInterfaces(): void {
        $objectTypes = $this->language->catalog->types;
        self::assertSame(["animal", "creature"], array_map(strval(...), $objectTypes->ancestors("hero")));
        self::assertSame(["holder"], array_map(strval(...), $objectTypes->ancestors("bag")));
        self::assertSame([], $objectTypes->ancestors("creature"));
        self::assertSame([], $objectTypes->ancestors("string"));
    }
}
