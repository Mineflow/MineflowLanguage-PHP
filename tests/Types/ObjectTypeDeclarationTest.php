<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Types;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\LanguageBuilder;
use PHPUnit\Framework\TestCase;

final class Item {
    public function __construct(public string $label) {
    }
}

#[DeclareType(Item::class)]
final class ItemType {
    #[Property]
    public function label(Item $item): string {
        return $item->label;
    }
}

#[ExtendType(Item::class)]
final class ItemExtras {
    #[Method]
    public function shout(Item $item): string {
        return strtoupper($item->label)."!";
    }
}

/**
 * Registering a PHP class as a type with #[DeclareType], and the declarations that are refused.
 */
final class ObjectTypeDeclarationTest extends TestCase {
    public function testATypeIsNamedAfterItsClassUnlessGivenAName(): void {
        $language = (new LanguageBuilder())->addType(new ItemType())->build();
        $scope = $language->scope(["item" => new Item("sword")]);

        self::assertSame("Item", (string)$language->evaluate("item", $scope)->type);
        self::assertSame("sword", $language->evaluate("item.label", $scope)->native());
    }

    public function testRedeclaringABuiltinTypePointsAtExtendType(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Use #[ExtendType(Builtin::String)]");

        (new LanguageBuilder())->addType(new #[DeclareType(Item::class, name: "string")]
        class {
        })->build();
    }

    public function testRedeclaringAnObjectTypePointsAtExtendTypeWithItsClass(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate type: Item is already declared for ".Item::class
            .". Use #[ExtendType(".Item::class."::class)] to add members to it, or give this type another name.");

        (new LanguageBuilder())
            ->addType(new ItemType())
            ->addType(new #[DeclareType(self::class, name: "Item")]
            class {
            })
            ->build();
    }

    public function testATypeCannotTakeTheNameOfASpecialType(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage('Reserved type name: "mixed"');

        (new LanguageBuilder())->addType(new #[DeclareType(Item::class, name: "mixed")]
        class {
        })->build();
    }

    public function testATypeNameMustBeAWord(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage('A type name is a word of letters, digits and _, not starting with a digit: "list<number>"');

        (new LanguageBuilder())->addType(new #[DeclareType(Item::class, name: "list<number>")]
        class {
        })->build();
    }

    public function testAnExtensionOfAnUnregisteredClassIsRejected(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Unregistered extension target: ".Item::class);

        (new LanguageBuilder())->addExtension(new ItemExtras())->build();
    }

    public function testAClassThatDoesNotExistIsRejected(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Unknown class on #[DeclareType] of");

        // @phpstan-ignore argument.type
        (new LanguageBuilder())->addType(new #[DeclareType("Mineflow\Language\Tests\Types\NoSuchItem")]
        class {
        })->build();
    }

    public function testOneClassCannotBackTwoTypes(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage(Item::class." is already represented by the type Item");

        (new LanguageBuilder())
            ->addType(new ItemType())
            ->addType(new #[DeclareType(Item::class, name: "duplicate")]
            class {
            })
            ->build();
    }
}
