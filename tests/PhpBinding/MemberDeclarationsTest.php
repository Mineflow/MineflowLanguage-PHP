<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\PhpBinding;

use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Values\StringValue;
use PHPUnit\Framework\TestCase;

/**
 * Which PHP methods the attributes can expose, and as what.
 */
final class MemberDeclarationsTest extends TestCase {
    public function testOneMethodCanBeReachedAsBothAPropertyAndAMethod(): void {
        $extension = new #[ExtendType(Builtin::String)]
        class {
            #[Property(name: "chars", aliases: ["size"])]
            #[Method(name: "chars")]
            public function count(string $value): int {
                return mb_strlen($value);
            }
        };

        $language = (new LanguageBuilder())->addExtension($extension)->build();
        $scope = $language->scope(["s" => "aieuo"]);

        self::assertSame(5, $language->evaluate("s.chars", $scope)->native());
        self::assertSame(5, $language->evaluate("s.size", $scope)->native());
        self::assertSame(5, $language->evaluate("s.chars()", $scope)->native());
    }

    public function testAPropertyCannotRequireArguments(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("No language arguments for property.");

        (new LanguageBuilder())->addExtension(new #[ExtendType(Builtin::String)]
        class {
            #[Property]
            public function repeated(string $value, int $times): string {
                return str_repeat($value, $times);
            }
        })->build();
    }

    public function testAClassAddedAsFunctionsOnlyDeclaresMethods(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("A class added with addFunctions() can only declare #[Method].");

        (new LanguageBuilder())->addFunctions(new class {
            #[Property]
            public function now(): int {
                return 0;
            }
        })->build();
    }

    public function testAnExposedMemberMustBeAPublicInstanceMethod(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("An exposed member must be a public instance method");

        (new LanguageBuilder())->addExtension(new #[ExtendType(Builtin::String)]
        class {
            #[Method]
            public static function shout(string $value): string {
                return strtoupper($value);
            }
        })->build();
    }

    public function testAnExtensionHasToSayWhichTypeItExtends(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Missing #[ExtendType] on");

        (new LanguageBuilder())->addExtension(new class {
            #[Method]
            public function shout(string $value): string {
                return strtoupper($value);
            }
        })->build();
    }

    public function testTheTargetValueCannotBeVariadic(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("The target value must be the type member's first ordinary parameter.");

        (new LanguageBuilder())->addExtension(new #[ExtendType(Builtin::String)]
        class {
            #[Method]
            public function first(string ...$values): string {
                return $values[0];
            }
        })->build();
    }

    public function testTheContextCannotTakeTheTargetValuesPlace(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("The target value must be the type member's first ordinary parameter.");

        (new LanguageBuilder())->addExtension(new #[ExtendType(Builtin::String)]
        class {
            #[Method]
            public function shout(#[FromContext] EvaluationContext $context, string $value): string {
                return $context->text(new StringValue($value));
            }
        })->build();
    }
}
