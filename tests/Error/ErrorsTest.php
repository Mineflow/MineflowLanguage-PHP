<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Error;

use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Definition\MemberKind;
use Mineflow\Language\Diagnostics\Notice;
use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Error\EvaluationError;
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Error\SourceError;
use Mineflow\Language\Error\UndefinedMember;
use Mineflow\Language\Error\UndefinedVariable;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Runtime\Scope;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class Unreliable {
    #[Method]
    public function fail(): string {
        throw new RuntimeException("the host blew up");
    }
}

/**
 * An error defined by the host.
 */
final class OutOfStock extends EvaluationError {
    public function __construct(public readonly string $item) {
        parent::__construct("$item is out of stock");
    }
}

/**
 * A host member that returns a PHP object rather than a Value of a declared type.
 */
final class Leaky {
    #[Method]
    public function leak(): mixed {
        return new Shop();
    }
}

final class Shop {
    #[Method]
    public function buy(string $item): string {
        throw new OutOfStock($item);
    }
}

final class ErrorsTest extends TestCase {
    public function testAnEvaluationErrorCarriesItsFactsAndRange(): void {
        $error = $this->errorFrom(Language::create(), "1 + missing");

        self::assertInstanceOf(UndefinedVariable::class, $error);
        self::assertSame("missing", $error->name);
        self::assertSame([4, 11], [$error->start(), $error->end()]);
    }

    public function testWhatAMemberThrowsReachesTheCallerAsItIs(): void {
        $language = (new LanguageBuilder())->addFunctions(new Unreliable())->build();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("the host blew up");
        $language->evaluate("1 + fail()");
    }

    public function testAnErrorTheHostDefinesPassesThroughWithItsRange(): void {
        $language = (new LanguageBuilder())->addFunctions(new Shop())->build();
        $error = $this->errorFrom($language, "1 + buy(apple)");

        self::assertInstanceOf(OutOfStock::class, $error);
        self::assertSame("apple", $error->item);
        self::assertSame([4, 14], [$error->start(), $error->end()]);
    }

    public function testAValueTheLanguageCannotTakeIsNotWrapped(): void {
        $language = (new LanguageBuilder())->addFunctions(new Leaky())->build();

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage("No language value for a PHP object of ".Shop::class."; pass a Value of its type");
        $language->evaluate("1 + leak()");
    }

    public function testANameNothingAnswersToSaysWhatItLookedFor(): void {
        $language = Language::create();
        $scope = $language->scope(["word" => "aieuo"]);
        $cases = [
            "word.missing()" => [MemberKind::Method, "Undefined method: string.missing"],
            "missing(1)" => [MemberKind::Function, "Undefined function: missing"],
        ];

        foreach ($cases as $source => [$kind, $message]) {
            $error = $this->errorFrom($language, $source, $scope);
            self::assertInstanceOf(UndefinedMember::class, $error, $source);
            self::assertSame($kind, $error->reference->kind, $source);
            self::assertSame($message, $error->getMessage());
        }

        $error = $this->errorFrom($language, "word.missing()", $scope);

        self::assertInstanceOf(UndefinedMember::class, $error);
        self::assertSame(["string", "missing"], [$error->reference->typeName, $error->reference->name]);
    }

    public function testAPropertyNothingGivesIsNullWithANoticeOfTheType(): void {
        $language = Language::create();
        $scope = $language->scope(["scores" => [3, 1]]);
        $notices = new Notices();

        self::assertNull($language->evaluate("scores.first().missing", $scope, $notices)->native());
        self::assertSame(["A value of the type number has no property missing, so null is used."], array_map(
            static fn(Notice $notice) => $notice->message,
            $notices->all(),
        ));
        self::assertSame([0, 22], [$notices->all()[0]->start, $notices->all()[0]->end]);
    }

    public function testAMemberOfNullIsNullWithoutANotice(): void {
        $language = Language::create();
        $scope = $language->scope(["user" => ["name" => "aieuo"]]);
        $notices = new Notices();

        self::assertNull($language->evaluate("user.missing.name", $scope, $notices)->native());
        self::assertNull($language->evaluate("user.missing.uppercase()", $scope, $notices)->native());
        self::assertSame([], $notices->all());
    }

    private function errorFrom(Language $language, string $source, ?Scope $scope = null): SourceError {
        try {
            $language->evaluate($source, $scope);
        } catch (SourceError $error) {
            return $error;
        }

        self::fail("Evaluating `$source` should have failed.");
    }
}
