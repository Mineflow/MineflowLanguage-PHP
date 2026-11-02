<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime\Engine;

use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Error\ArgumentTypeMismatch;
use Mineflow\Language\Error\InvalidIntegerConversion;
use Mineflow\Language\Error\MissingArgument;
use Mineflow\Language\Error\SourceError;
use Mineflow\Language\Error\TooManyArguments;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Values\StringValue;
use PHPUnit\Framework\TestCase;

final class Padding {
    #[Method]
    public function pad(string $value, int $width): string {
        return str_pad($value, $width);
    }

    #[Method]
    public function tag(string ...$parts): string {
        return implode("|", $parts);
    }

    /**
     * @param list<string> $names
     */
    #[Method]
    public function total(#[Param(type: new ListOf(StringValue::class))] array $names): int {
        return count($names);
    }
}

/**
 * Arguments that can be passed to declared members, and the errors when they don't match.
 */
final class ArgumentsTest extends TestCase {
    private Language $language;

    protected function setUp(): void {
        $this->language = (new LanguageBuilder())->addFunctions(new Padding())->build();
    }

    private function refusal(string $source): SourceError {
        try {
            $this->language->evaluate($source);
        } catch (SourceError $error) {
            return $error;
        }

        self::fail("Expected $source to be refused.");
    }

    public function testAParameterWithoutADefaultHasToBePassed(): void {
        $error = $this->refusal("pad(x)");

        self::assertInstanceOf(MissingArgument::class, $error);
        self::assertSame("Missing argument: width", $error->getMessage());
        self::assertSame(["pad", "width"], [$error->reference->name, $error->parameter]);
    }

    public function testAnExtraArgumentIsRefusedAndNamesTheMember(): void {
        $error = $this->refusal("pad(x, 4, 5)");

        self::assertInstanceOf(TooManyArguments::class, $error);
        self::assertSame("Too many arguments for pad", $error->getMessage());
        self::assertSame(["pad", 3], [$error->reference->name, $error->givenCount]);
    }

    public function testAVariadicParameterTakesEveryRemainingArgument(): void {
        self::assertSame("", $this->language->evaluate("tag()")->native());
        self::assertSame("a|b|c", $this->language->evaluate("tag(a, b, c)")->native());
    }

    public function testAVariadicParameterStillChecksEachArgument(): void {
        $error = $this->refusal("tag(a, {1})");

        self::assertInstanceOf(ArgumentTypeMismatch::class, $error);
        self::assertSame("parts expects string, got number", $error->getMessage());
        self::assertSame(["parts", "string", "number"], [$error->parameter, (string)$error->expected, (string)$error->actual]);
    }

    public function testAnIntegerParameterTakesANumberThatHasNoFraction(): void {
        self::assertSame("x ", $this->language->evaluate("pad(x, 2.0)")->native());

        $error = $this->refusal("pad(x, {1.5})");
        self::assertInstanceOf(InvalidIntegerConversion::class, $error);
        self::assertSame(1.5, $error->actual);
    }

    public function testAListReachesAnArrayParameterAsAPhpList(): void {
        $scope = $this->language->scope(["names" => ["a", "b"]]);

        self::assertSame(2, $this->language->evaluate("total({names})", $scope)->native());
    }
}
