<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Definition;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Definition\Catalog;
use Mineflow\Language\Definition\Declaration;
use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Definition\MemberKind;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Definition\Parameter;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Tests\Fixtures\SettingsValue;
use Mineflow\Language\Types\TypeRegistry;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

#[DeclareType("badge")]
final readonly class Badge extends Value {
    public function __construct(public string $label = "gold") {
    }

    #[Property(description: "The label shown on the badge.")]
    public function label(): string {
        return $this->label;
    }

    #[Method(aliases: ["repeated"], description: "The label, repeated.")]
    public function repeat(int $times = 2): string {
        return str_repeat($this->label, $times);
    }

    public function __toString(): string {
        return "badge";
    }
}

final class Greetings {
    #[Method(aliases: ["hi"])]
    public function hello(string $name): string {
        return "hello $name";
    }
}

final class CatalogTest extends TestCase {
    public function testABrokenDeclarationIsRefused(): void {
        $text = new Parameter("text", StandardTypes::string());
        $slot = new Parameter("slot", StandardTypes::number());
        $cases = [
            "Unknown type for method player.m" => [MemberReference::method("player", "m"), []],
            "No language arguments for property: string.p" => [MemberReference::property("string", "p"), [$text]],
            "A binary operator requires exactly one right operand: string.+" => [MemberReference::operator("string", BinaryOperator::Add), []],
            "A binary operator requires exactly one left operand: string.+" => [MemberReference::reflectedOperator("string", BinaryOperator::Add), []],
            "An index takes exactly one name, as a string: settings.*" => [MemberReference::index("settings"), [$slot]],
        ];
        $types = new TypeRegistry([...StandardTypes::CLASSES, SettingsValue::class]);

        foreach ($cases as $message => [$reference, $parameters]) {
            try {
                Catalog::from($types, [$this->declare($reference, $parameters)]);

                self::fail("Expected the catalog to refuse: $message");
            } catch (DefinitionError $error) {
                self::assertStringContainsString($message, $error->getMessage());
            }
        }
    }

    public function testANameIsTakenOncePerTypeAndKind(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate method: string.shout");

        Catalog::from(new TypeRegistry(StandardTypes::CLASSES), [
            $this->declare(MemberReference::method("string", "shout")),
            $this->declare(MemberReference::property("string", "shout")),
            $this->declare(MemberReference::method("string", "shout")),
        ]);
    }

    public function testAFunctionNameIsTakenOnceAmongFunctions(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate function: shout");

        Catalog::from(new TypeRegistry(StandardTypes::CLASSES), [
            $this->declare(MemberReference::method("string", "shout")),
            $this->declare(MemberReference::function("shout")),
            $this->declare(MemberReference::function("shout")),
        ]);
    }

    public function testTheCatalogNamesEveryDeclaredType(): void {
        $types = $this->badges()->catalog->typeNames;

        self::assertContains("badge", $types);
        self::assertContains("string", $types);
    }

    public function testADeclarationCanBeLookedUpByKindAndName(): void {
        $catalog = $this->badges()->catalog;
        $label = $catalog->definition(MemberReference::property("badge", "label"));

        self::assertInstanceOf(MemberDefinition::class, $label);
        self::assertSame("The label shown on the badge.", $label->description);
        self::assertSame("string", (string)$label->returns);
        self::assertNull($catalog->definition(MemberReference::method("badge", "label")));
    }

    public function testTheSchemaCarriesEveryNameAMemberAnswersTo(): void {
        $catalog = $this->badges()->catalog;
        $badge = $catalog->schema()["types"]["badge"];

        self::assertSame(["label"], array_keys($badge[MemberKind::Property->value]));
        self::assertSame(["repeat", "repeated"], array_keys($badge[MemberKind::Method->value]));
        self::assertSame($badge, $catalog->members("badge"));
    }

    public function testFunctionsAreListedApartFromTypes(): void {
        $language = (new LanguageBuilder())->addFunctions(new Greetings())->build();
        $builtin = array_keys(Language::create()->catalog->functions());

        self::assertSame([...$builtin, "hello", "hi"], array_keys($language->catalog->functions()));
        self::assertSame($language->catalog->functions(), $language->catalog->schema()["functions"]);
        self::assertArrayNotHasKey("", $language->catalog->schema()["types"]);
    }

    public function testTheCatalogListsTheIndexAndTheMembersOfTheCollection(): void {
        $catalog = (new LanguageBuilder())->addType(SettingsValue::class)->build()->catalog;
        $members = $catalog->members("settings");

        self::assertSame("The item under that key, or else the property of that name read off every item; null when neither is there.", $members[MemberKind::Index->value][MemberReference::ANY]->description);
        self::assertSame("Declared here, so it wins over the collection's own first().", $members[MemberKind::Method->value]["first"]->description);
        self::assertSame($catalog->definition(MemberReference::method("collection", "keys")), $members[MemberKind::Method->value]["keys"]);
        self::assertSame(["list" => ["collection"], "map" => ["collection"], "settings" => ["collection"]], $catalog->schema()["ancestors"]);
    }

    public function testADeclarationSerialisesToJsonForHostTooling(): void {
        $repeat = $this->badges()->catalog->definition(MemberReference::method("badge", "repeat"));

        self::assertInstanceOf(MemberDefinition::class, $repeat);
        self::assertSame(
            ["returns" => "string", "parameters" => [["name" => "times", "type" => "number", "optional" => true, "variadic" => false]], "description" => "The label, repeated."],
            json_decode(json_encode($repeat, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR),
        );
    }

    private function badges(): Language {
        return (new LanguageBuilder())->addType(Badge::class)->build();
    }

    /**
     * @param list<Parameter> $parameters
     */
    private function declare(MemberReference $reference, array $parameters = []): Declaration {
        return new Declaration($reference, new MemberDefinition(StandardTypes::string(), $parameters));
    }
}
