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
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Placeholder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class Creature {
    public function __construct(public string $name, public int $health = 20) {
    }
}

class Animal extends Creature {
}

final class Hero extends Animal {
}

interface Holder {
}

interface Bag extends Holder {
    /**
     * @return list<string>
     */
    public function contents(): array;
}

final class Backpack implements Bag {
    /**
     * @param list<string> $items
     */
    public function __construct(private array $items) {
    }

    public function contents(): array {
        return $this->items;
    }
}

interface Labelled {
}

final class Crate implements Bag, Labelled {
    public function contents(): array {
        return [];
    }
}

#[DeclareType(Creature::class, name: "creature")]
final class CreatureType {
    #[Property]
    public function name(Creature $creature): string {
        return $creature->name;
    }

    #[Property]
    public function kind(Creature $creature): string {
        return "creature";
    }

    #[Property]
    public function sound(Creature $creature): string {
        return "...";
    }

    #[Method(type: Placeholder::Target, description: "The same creature.")]
    public function self(Creature $creature): Creature {
        return $creature;
    }

    #[Method]
    public function fail(Creature $creature): string {
        throw new RuntimeException("failed");
    }

    #[Operator(BinaryOperator::Add)]
    public function heal(Creature $creature, int $by): Creature {
        return new $creature($creature->name, $creature->health + $by);
    }

    #[Property]
    public function health(Creature $creature): int {
        return $creature->health;
    }

    #[Operator(BinaryOperator::Multiply)]
    public function times(Creature $creature, Creature $other): string {
        return "creature";
    }

    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function timesFrom(Creature $creature, Creature $other): string {
        return "creature reflected";
    }
}

#[DeclareType(Animal::class, name: "animal")]
final class AnimalType {
    #[Property]
    public function kind(Animal $animal): string {
        return "animal";
    }

    #[Property]
    public function sound(Animal $animal): string {
        return "growl";
    }
}

#[DeclareType(Hero::class, name: "hero")]
final class HeroType {
    #[Property]
    public function kind(Creature $hero): string {
        return "hero";
    }

    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function timesFrom(Hero $hero, Creature $other): string {
        return "hero";
    }
}

#[ExtendType(Creature::class)]
final class CreatureExtras {
    #[Method]
    public function shout(Creature $creature): string {
        return strtoupper($creature->name)."!";
    }
}

#[DeclareType(Holder::class, name: "holder")]
final class HolderType {
    #[Property]
    public function kind(Holder $holder): string {
        return "holder";
    }

    #[Index]
    public function slot(Holder $holder, string $slot): ?string {
        return $holder instanceof Bag ? $holder->contents()[(int)$slot] ?? null : null;
    }
}

#[DeclareType(Bag::class, name: "bag")]
final class BagType {
    #[Property]
    public function size(Bag $bag): int {
        return count($bag->contents());
    }
}

#[DeclareType(Labelled::class, name: "labelled")]
final class LabelledType {
}

interface Operand {
}

class OperatorBase {
}

final class OperatorChild extends OperatorBase implements Operand {
}

final class LooseOperand implements Operand {
}

#[DeclareType(Operand::class, name: "operand")]
final class OperandType {
    #[Operator(BinaryOperator::Multiply)]
    public function times(Operand $operand, Operand $other): string {
        return "operand";
    }
}

#[DeclareType(OperatorBase::class, name: "operator_base")]
final class OperatorBaseType {
    #[Operator(BinaryOperator::Multiply, reflected: true)]
    public function timesFrom(OperatorBase $base, Operand $other): string {
        return "base reflected";
    }
}

#[DeclareType(OperatorChild::class, name: "operator_child")]
final class OperatorChildType {
}

interface Tagged {
}

/**
 * @extends \IteratorAggregate<int, string>
 */
interface Sealed extends \Countable, \IteratorAggregate, \JsonSerializable {
}

final class Parcel implements Sealed, Tagged {
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
}

final class Pouch implements Bag {
    public function contents(): array {
        return ["coin"];
    }
}

#[DeclareType(Tagged::class, name: "tagged")]
final class TaggedType {
    #[Property]
    public function label(Tagged $tagged): string {
        return "tagged";
    }
}

#[DeclareType(Sealed::class, name: "sealed")]
final class SealedType {
    #[Property]
    public function label(Sealed $sealed): string {
        return "sealed";
    }
}

#[DeclareType(Parcel::class, name: "parcel")]
final class ParcelType {
}

#[DeclareType(Pouch::class, name: "pouch")]
final class PouchType {
}

final class HierarchyFunctions {
    #[Method]
    public function greet(Creature $creature): string {
        return "hi ".$creature->name;
    }

    #[Method]
    public function pet(Animal $animal): string {
        return "petted ".$animal->name;
    }

    #[Method]
    public function weigh(Holder $holder): string {
        return "weighed";
    }
}

final class ObjectTypeHierarchyTest extends TestCase {
    private Language $language;

    protected function setUp(): void {
        $this->language = (new LanguageBuilder())
            ->addType(new CreatureType())
            ->addType(new AnimalType())
            ->addType(new HeroType())
            ->addType(new HolderType())
            ->addType(new BagType())
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
        $hero = new Hero("aieuo");

        self::assertSame("aieuo", $this->evaluate("h.name", ["h" => $hero]));
        self::assertSame("AIEUO!", $this->evaluate("h.shout()", ["h" => $hero]));
    }

    public function testTheNearestDeclarationWins(): void {
        $hero = new Hero("aieuo");

        self::assertSame("hero", $this->evaluate("h.kind", ["h" => $hero]));
        self::assertSame("growl", $this->evaluate("h.sound", ["h" => $hero]));
        self::assertSame("...", $this->evaluate("c.sound", ["c" => new Creature("slime")]));
    }

    public function testAnInheritedMemberIsCalledOnTheSubtype(): void {
        $scope = $this->language->scope(["h" => new Hero("aieuo")]);

        self::assertSame("hero", (string)$this->language->evaluate("h.self()", $scope)->type);
        self::assertSame("hero", (string)$this->language->evaluate("h + 5", $scope)->type);
        self::assertSame(25, $this->language->evaluate("(h + 5).health", $scope)->native());
    }

    public function testAFailureOfAnInheritedMemberNamesTheSubtype(): void {
        try {
            $this->evaluate("h.fail()", ["h" => new Hero("aieuo")]);

            self::fail("Expected an ExternalFailure.");
        } catch (ExternalFailure $error) {
            self::assertSame("hero.fail", (string)$error->reference);
        }
    }

    public function testASubtypeIsAcceptedWhereItsAncestorIsExpected(): void {
        self::assertSame("hi aieuo", $this->evaluate("greet({h})", ["h" => new Hero("aieuo")]));
        self::assertSame("petted aieuo", $this->evaluate("pet({h})", ["h" => new Hero("aieuo")]));
    }

    public function testAnAncestorIsNotAcceptedWhereItsSubtypeIsExpected(): void {
        $this->expectException(ArgumentTypeMismatch::class);
        $this->evaluate("pet({c})", ["c" => new Creature("slime")]);
    }

    public function testAnObjectOfAnUndeclaredClassIsWrappedAsTheMostSpecificInterface(): void {
        $scope = $this->language->scope(["b" => new Backpack(["apple", "stone"])]);

        self::assertSame("bag", (string)$this->language->evaluate("b", $scope)->type);
        self::assertSame(2, $this->language->evaluate("b.size", $scope)->native());
        self::assertSame("holder", $this->language->evaluate("b.kind", $scope)->native());
        self::assertSame("stone", $this->language->evaluate("b.1", $scope)->native());
        self::assertSame("weighed", $this->language->evaluate("weigh({b})", $scope)->native());
    }

    public function testAnObjectMatchingTwoUnrelatedInterfacesHasNoType(): void {
        $language = (new LanguageBuilder())->addType(new BagType())->addType(new LabelledType())->build();

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage(Crate::class." implements the interfaces of the types bag, labelled alike; declare a type for its class.");
        $language->scope(["c" => new Crate()]);
    }

    public function testASubtypeOnTheRightWithItsOwnReflectedOperatorIsAskedFirst(): void {
        $operands = ["c" => new Creature("slime"), "a" => new Animal("wolf"), "h" => new Hero("aieuo")];

        self::assertSame("hero", $this->evaluate("c * h", $operands));
        self::assertSame("hero", $this->evaluate("a * h", $operands));
        self::assertSame("creature", $this->evaluate("c * a", $operands));
        self::assertSame("creature", $this->evaluate("h * c", $operands));
        self::assertSame("creature", $this->evaluate("h * h", $operands));
    }

    public function testAReflectedOperatorFromAnAncestorThatIsNotASubtypeOfTheLeftTypeIsNotAskedFirst(): void {
        $language = (new LanguageBuilder())
            ->addType(new OperandType())
            ->addType(new OperatorBaseType())
            ->addType(new OperatorChildType())
            ->build();

        $scope = $language->scope(["o" => new LooseOperand(), "c" => new OperatorChild()]);
        self::assertSame("operand", (string)$language->evaluate("o", $scope)->type);
        self::assertSame("operand", $language->evaluate("o * c", $scope)->native());
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
        self::assertNull($catalog->origin(MemberReference::property("hero", "level")));
        self::assertSame("hero.name", (string)$catalog->declaration(MemberReference::property("hero", "name"))?->reference);
    }

    public function testTheSchemaHoldsEveryMemberOnceAndTheAncestorsOfEveryType(): void {
        $schema = $this->language->catalog->schema();
        self::assertSame(["kind"], array_keys($schema["types"]["hero"][MemberKind::Property->value]));
        self::assertSame(["animal" => ["creature"], "hero" => ["animal", "creature"], "bag" => ["holder"]], $schema["ancestors"]);
    }

    public function testUnrelatedInterfacesAreInheritedInTheOrderTheyWereDeclared(): void {
        $language = (new LanguageBuilder())->addType(new TaggedType())->addType(new SealedType())->addType(new ParcelType())->build();
        self::assertSame(["tagged", "sealed"], array_map(strval(...), $language->catalog->objectTypes->ancestors("parcel")));
        self::assertSame("tagged", $language->evaluate("p.label", $language->scope(["p" => new Parcel()]))->native());

        $language = (new LanguageBuilder())->addType(new SealedType())->addType(new TaggedType())->addType(new ParcelType())->build();
        self::assertSame(["sealed", "tagged"], array_map(strval(...), $language->catalog->objectTypes->ancestors("parcel")));
        self::assertSame("sealed", $language->evaluate("p.label", $language->scope(["p" => new Parcel()]))->native());
    }

    public function testAnInterfaceComesBeforeOneItExtendsWhicheverWasDeclaredFirst(): void {
        foreach ([[new HolderType(), new BagType()], [new BagType(), new HolderType()]] as $interfaces) {
            $builder = new LanguageBuilder();

            foreach ([...$interfaces, new PouchType()] as $type) {
                $builder->addType($type);
            }
            self::assertSame(["bag", "holder"], array_map(strval(...), $builder->build()->catalog->objectTypes->ancestors("pouch")));
        }
    }

    public function testAncestorsAreTheParentClassesThenTheInterfaces(): void {
        $objectTypes = $this->language->catalog->objectTypes;
        self::assertSame(["animal", "creature"], array_map(strval(...), $objectTypes->ancestors("hero")));
        self::assertSame(["holder"], array_map(strval(...), $objectTypes->ancestors("bag")));
        self::assertSame([], $objectTypes->ancestors("creature"));
        self::assertSame([], $objectTypes->ancestors("string"));
    }
}
