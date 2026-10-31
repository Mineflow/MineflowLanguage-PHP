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
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\MemberImplementation;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Tests\Fixtures\Settings;
use Mineflow\Language\Tests\Fixtures\SettingsType;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\ObjectTypes;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Value;
use PHPUnit\Framework\TestCase;

/**
 * Members implemented without attributes.
 */
final class Shout implements MemberImplementation {
    public function call(?Value $target, array $arguments, EvaluationContext $context): Value {
        return Value::from(strtoupper($context->text($target ?? $arguments[0])));
    }
}

#[DeclareType(Badge::class, name: "badge")]
final class Badge {
    public function __construct(public string $label = "gold") {
    }

    #[Property(description: "The label shown on the badge.")]
    public function label(self $badge): string {
        return $badge->label;
    }

    #[Method(aliases: ["repeated"], description: "The label, repeated.")]
    public function repeat(self $badge, int $times = 2): string {
        return str_repeat($badge->label, $times);
    }
}

final class Greetings {
    #[Method(aliases: ["hi"])]
    public function hello(string $name): string {
        return "hello $name";
    }
}

final class CatalogTest extends TestCase {
    public function testAMemberNeedsNoAttributeToBeCalled(): void {
        $catalog = Catalog::from(new ObjectTypes(), [
            $this->declare(MemberReference::method("string", "shout")),
            $this->declare(MemberReference::function("shout"), [new Parameter("text", Type::string())]),
        ]);
        $language = new Language($catalog);
        $scope = $language->scope(["word" => "hi"]);

        self::assertSame("HI", $language->evaluate("word.shout()", $scope)->native());
        self::assertSame("HEY", $language->evaluate("shout(hey)")->native());
    }

    public function testABrokenDeclarationIsRefused(): void {
        $text = new Parameter("text", Type::string());
        $slot = new Parameter("slot", Type::number());
        $cases = [
            "Unknown type for method player.m" => [MemberReference::method("player", "m"), []],
            "No language arguments for property: string.p" => [MemberReference::property("string", "p"), [$text]],
            "A binary operator requires exactly one right operand: string.+" => [MemberReference::operator("string", BinaryOperator::Add), []],
            "A binary operator requires exactly one left operand: string.+" => [MemberReference::reflectedOperator("string", BinaryOperator::Add), []],
            "An index takes exactly one name, as a string: settings.*" => [MemberReference::index("settings"), [$slot]],
            "Entries are either a list or a map: settings.*" => [MemberReference::entries("settings"), []],
            "A built-in type cannot declare index: map.*" => [MemberReference::index("map"), [$text]],
        ];
        $objectTypes = new ObjectTypes(Type::object("settings", Settings::class));

        foreach ($cases as $message => [$reference, $parameters]) {
            try {
                Catalog::from($objectTypes, [$this->declare($reference, $parameters)]);

                self::fail("Expected the catalog to refuse: $message");
            } catch (DefinitionError $error) {
                self::assertStringContainsString($message, $error->getMessage());
            }
        }
    }

    public function testANameIsTakenOncePerTypeAndKind(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate method: string.shout");

        Catalog::from(new ObjectTypes(), [
            $this->declare(MemberReference::method("string", "shout")),
            $this->declare(MemberReference::property("string", "shout")),
            $this->declare(MemberReference::method("string", "shout")),
        ]);
    }

    public function testAFunctionNameIsTakenOnceAmongFunctions(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate function: shout");

        Catalog::from(new ObjectTypes(), [
            $this->declare(MemberReference::method("string", "shout")),
            $this->declare(MemberReference::function("shout")),
            $this->declare(MemberReference::function("shout")),
        ]);
    }

    public function testTheCatalogNamesEveryDeclaredType(): void {
        $types = $this->badges()->catalog->typeNames;

        self::assertContains("badge", $types);
        self::assertContains(Builtin::String->value, $types);
    }

    public function testADeclarationCanBeLookedUpByKindAndName(): void {
        $catalog = $this->badges()->catalog;
        $label = $catalog->definition(MemberReference::property("badge", "label"));

        self::assertInstanceOf(MemberDefinition::class, $label);
        self::assertSame("The label shown on the badge.", $label->description);
        self::assertSame(Builtin::String->value, (string)$label->returns);
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

    public function testTheCatalogListsTheIndexAndTheEntries(): void {
        $catalog = (new LanguageBuilder())->addType(new SettingsType())->build()->catalog;
        $members = $catalog->members("settings");

        self::assertSame("The value under a key.", $members[MemberKind::Index->value][MemberReference::ANY]->description);
        self::assertSame("map<string,mixed>", (string)$members[MemberKind::Entries->value][MemberReference::ANY]->returns);
        self::assertNotNull($catalog->definition(MemberReference::index("settings")));
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
        return (new LanguageBuilder())->addType(new Badge())->build();
    }

    /**
     * @param list<Parameter> $parameters
     */
    private function declare(MemberReference $reference, array $parameters = []): Declaration {
        return new Declaration($reference, new MemberDefinition(Type::string(), $parameters), new Shout());
    }
}
