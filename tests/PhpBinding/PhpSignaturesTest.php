<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\PhpBinding;

use Countable;
use LogicException;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Types\Expression\Nullable;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;
use Stringable;

final class Signatures {
    #[Method]
    public function yes(): bool {
        return true;
    }

    #[Method]
    public function nothing(): null {
        return null;
    }

    #[Method]
    public function any(): mixed {
        return "whatever";
    }

    #[Method]
    public function maybe(bool $empty): ?string {
        return $empty ? null : "here";
    }

    #[Method]
    public function boom(): never {
        throw new LogicException("declared, never called");
    }

    #[Method]
    public function half(bool $whole): int|float {
        return $whole ? 1 : 0.5;
    }
}

final class PhpSignaturesTest extends TestCase {
    private function refuse(string $message, object $functions): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage($message);

        (new LanguageBuilder())->addFunctions($functions)->build();
    }

    public function testAPhpTypeBecomesTheLanguageTypeOfTheSameName(): void {
        $language = (new LanguageBuilder())->addFunctions(new Signatures())->build();

        self::assertSame("boolean", (string)$language->evaluate("yes()")->type);
        self::assertSame("null", (string)$language->evaluate("nothing()")->type);
        self::assertSame("string", (string)$language->evaluate("any()")->type);
    }

    public function testAPhpUnionBecomesTheLanguageUnionOfItsParts(): void {
        $catalog = (new LanguageBuilder())->addFunctions(new Signatures())->build()->catalog;

        // int|float becomes the single language type number, and a nullable type becomes a union with null.
        self::assertSame("number", (string)$catalog->definition(MemberReference::function("half"))?->returns);
        self::assertSame("null|string", (string)$catalog->definition(MemberReference::function("maybe"))?->returns);
        self::assertSame("never", (string)$catalog->definition(MemberReference::function("boom"))?->returns);
    }

    public function testAMemberWithoutAPhpReturnTypeIsRefused(): void {
        $this->refuse("Missing PHP return type in", new class {
            /**
             * @return string
             */
            #[Method]
            public function guess(string $value) {
                return $value;
            }
        });
    }

    public function testADeclaredTypeThePhpSignatureContradictsIsRefused(): void {
        $this->refuse("list<string> conflicts with PHP type int in", new class {
            #[Method(type: new ListOf(Builtin::String))]
            public function names(): int {
                return 1;
            }
        });
    }

    public function testADeclaredTypeThePhpSignatureAlreadySaysIsRefused(): void {
        $this->refuse("The PHP type ?string already says null|string", new class {
            #[Method(type: new Nullable(Builtin::String))]
            public function label(int $value): ?string {
                return $value > 0 ? "positive" : null;
            }
        });
    }

    public function testARawValueHasToSayWhatLanguageTypeItCarries(): void {
        $this->refuse("Specify the language type for Value in", new class {
            #[Method]
            public function wrapped(): Value {
                return new NumberValue(1);
            }
        });
    }

    public function testAPhpArrayHasToSayWhatItHolds(): void {
        $this->refuse("Specify a list/map element type with #[Param(type: ...)] in", new class {
            /**
             * @return list<string>
             */
            #[Method]
            public function items(): array {
                return [];
            }
        });
    }

    public function testAValueClassStandsForTheKindOfValueItHolds(): void {
        $language = (new LanguageBuilder())->addFunctions(new class {
            #[Method]
            public function size(?CollectionValue $items): int {
                return $items === null ? -1 : count($items->items());
            }

            #[Method]
            public function either(StringValue|NumberValue $value): string {
                return $value::class;
            }

            #[Method]
            public function names(#[Param(type: new ListOf(Builtin::String))] ListValue $names): MapValue {
                return new MapValue(["names" => $names]);
            }
        })->build();

        $parameter = static fn(string $name) => (string)$language->catalog->definition(MemberReference::function($name))?->parameters[0]->type;

        self::assertSame("list|map|null", $parameter("size"));
        self::assertSame("number|string", $parameter("either"));
        self::assertSame("list<string>", $parameter("names"));
        self::assertSame(NumberValue::class, $language->evaluate("either(1)")->native());
        self::assertSame("map", (string)$language->catalog->definition(MemberReference::function("names"))?->returns);
        self::assertSame(2, $language->evaluate("size({items})", $language->scope(["items" => ["a" => 1, "b" => 2]]))->native());
        self::assertSame(-1, $language->evaluate("size({items})", $language->scope(["items" => null]))->native());
        self::assertSame(["names" => ["a"]], $language->evaluate("names({items})", $language->scope(["items" => ["a"]]))->native());
    }

    public function testACollectionValueNeedsNoTypeThatOnlyRepeatsIt(): void {
        $this->refuse("already says list in", new class {
            #[Method]
            public function size(#[Param(type: Builtin::List)] ListValue $list): int {
                return count($list->items());
            }
        });
    }

    public function testAnIntersectionTypeHasNoLanguageCounterpart(): void {
        $this->refuse("Unsupported PHP type Countable&Stringable in", new class {
            #[Method]
            public function both(): Countable&Stringable {
                throw new LogicException("not built");
            }
        });
    }
}
